package tunnel

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net"
	"sort"
	"strconv"
	"sync"
	"sync/atomic"
)

type clientSession struct {
	connection net.Conn
	encoder    *json.Encoder
	writeMu    sync.Mutex
}

func (c *clientSession) send(message Message) error {
	c.writeMu.Lock()
	defer c.writeMu.Unlock()
	return c.encoder.Encode(message)
}

type stream struct {
	public net.Conn
	client *clientSession
}

type Server struct {
	address       string
	publicAddress string
	authToken     string
	clients       map[string]*clientSession
	streams       map[string]*stream
	mu            sync.RWMutex
	nextStreamID  uint64
}

func NewServer(address string) *Server {
	return NewServerWithPublicAddress(address, "")
}

// NewServerWithPublicAddress keeps the control listener API while enabling TCP exposure.
func NewServerWithPublicAddress(address, publicAddress string) *Server {
	return &Server{
		address: address, publicAddress: publicAddress,
		clients: make(map[string]*clientSession), streams: make(map[string]*stream),
	}
}

func (s *Server) SetAuthToken(token string) {
	s.authToken = token
}

func (s *Server) ConnectedClients() []string {
	s.mu.RLock()
	clients := make([]string, 0, len(s.clients))
	for name := range s.clients { clients = append(clients, name) }
	s.mu.RUnlock()
	sort.Strings(clients)
	return clients
}

func (s *Server) registerClient(name string, client *clientSession) {
	s.mu.Lock()
	s.clients[name] = client
	count := len(s.clients)
	s.mu.Unlock()
	fmt.Printf("Active clients: %d\n", count)
}

func (s *Server) unregisterClient(name string, client *clientSession) {
	s.mu.Lock()
	if current, ok := s.clients[name]; ok && current == client { delete(s.clients, name) }
	s.mu.Unlock()
	fmt.Printf("Active clients: %d\n", len(s.ConnectedClients()))
}

func (s *Server) ListenAndServe() error {
	listener, err := net.Listen("tcp", s.address)
	if err != nil { return err }
	defer listener.Close()
	fmt.Printf("Listening control %s\n", s.address)
	if s.publicAddress != "" {
		publicListener, err := net.Listen("tcp", s.publicAddress)
		if err != nil { return err }
		fmt.Printf("Listening public %s\n", s.publicAddress)
		go s.acceptPublic(publicListener)
	}
	for {
		connection, err := listener.Accept()
		if err != nil { return err }
		go s.handleConnection(connection)
	}
}

func (s *Server) handleConnection(connection net.Conn) {
	defer connection.Close()
	client := &clientSession{connection: connection, encoder: json.NewEncoder(connection)}
	decoder := json.NewDecoder(bufio.NewReader(connection))
	var hello Message
	if err := decoder.Decode(&hello); err != nil || hello.Type != MessageHello || hello.Name == "" { return }
	if s.authToken != "" && hello.Token != s.authToken { return }
	if err := client.send(Message{Type: MessageServerOK}); err != nil { return }
	s.registerClient(hello.Name, client)
	defer s.unregisterClient(hello.Name, client)
	fmt.Printf("Client connected: %s\n", hello.Name)
	for {
		var message Message
		if err := decoder.Decode(&message); err != nil { return }
		switch message.Type {
		case MessagePing:
			if err := client.send(Message{Type: MessagePong}); err != nil { return }
		case MessageData:
			s.forwardToPublic(message.StreamID, message.Payload)
		case MessageCloseStream:
			s.closeStream(message.StreamID, client)
		case MessageClose:
			return
		}
	}
}

func (s *Server) acceptPublic(listener net.Listener) {
	defer listener.Close()
	for {
		connection, err := listener.Accept()
		if err != nil { return }
		go s.handlePublic(connection)
	}
}

func (s *Server) handlePublic(connection net.Conn) {
	client := s.chooseClient()
	if client == nil { connection.Close(); return }
	id := strconv.FormatUint(atomic.AddUint64(&s.nextStreamID, 1), 10)
	s.mu.Lock()
	s.streams[id] = &stream{public: connection, client: client}
	s.mu.Unlock()
	if err := client.send(Message{Type: MessageOpenStream, StreamID: id}); err != nil {
		s.closeStream(id, client); return
	}
	defer s.closeStream(id, client)
	buffer := make([]byte, 32*1024)
	for {
		n, err := connection.Read(buffer)
		if n > 0 {
			payload := append([]byte(nil), buffer[:n]...)
			if err := client.send(Message{Type: MessageData, StreamID: id, Payload: payload}); err != nil { return }
		}
		if err != nil { return }
	}
}

func (s *Server) chooseClient() *clientSession {
	s.mu.RLock()
	defer s.mu.RUnlock()
	for _, client := range s.clients { return client }
	return nil
}

func (s *Server) forwardToPublic(id string, payload []byte) {
	s.mu.RLock(); current := s.streams[id]; s.mu.RUnlock()
	if current != nil && len(payload) > 0 { _, _ = current.public.Write(payload) }
}

func (s *Server) closeStream(id string, client *clientSession) {
	s.mu.Lock()
	current, ok := s.streams[id]
	if ok && current.client == client { delete(s.streams, id) } else { ok = false }
	s.mu.Unlock()
	if !ok { return }
	_ = current.public.Close()
	_ = client.send(Message{Type: MessageCloseStream, StreamID: id})
}
