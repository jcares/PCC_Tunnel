package tunnel

import (
	"bufio"
	"encoding/json"
	"fmt"
	"log"
	"net"
	"sort"
	"strconv"
	"sync"
	"sync/atomic"
	"time"
)

// ClientInfo describe un cliente registrado en el Gateway.
type ClientInfo struct {
	ID             string
	Name           string
	RemoteIP       string
	ConnectedAt    time.Time
	LastHeartbeat  time.Time
	Online         bool
}

type clientSession struct {
	info       ClientInfo
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

// Server es el núcleo del Gateway.
type Server struct {
	address          string
	publicAddress    string
	authToken        string
	heartbeatTimeout time.Duration
	logger           *log.Logger
	clients          map[string]*clientSession
	streams          map[string]*stream
	mu               sync.RWMutex
	nextStreamID     uint64
}

func NewServer(address string) *Server {
	return NewServerWithPublicAddress(address, "")
}

// NewServerWithPublicAddress construye el Server con control y escucha pública separados.
func NewServerWithPublicAddress(address, publicAddress string) *Server {
	return &Server{
		address:          address,
		publicAddress:    publicAddress,
		heartbeatTimeout: 15 * time.Second,
		logger:           log.Default(),
		clients:          make(map[string]*clientSession),
		streams:          make(map[string]*stream),
	}
}

func (s *Server) SetAuthToken(token string) { s.authToken = token }

func (s *Server) SetHeartbeatTimeout(d time.Duration) {
	if d > 0 {
		s.heartbeatTimeout = d
	}
}

func (s *Server) SetLogger(l *log.Logger) {
	if l != nil {
		s.logger = l
	}
}

// ConnectedClients devuelve los nombres de clientes online ordenados.
func (s *Server) ConnectedClients() []string {
	s.mu.RLock()
	names := make([]string, 0, len(s.clients))
	for name := range s.clients {
		names = append(names, name)
	}
	s.mu.RUnlock()
	sort.Strings(names)
	return names
}

// Clients devuelve información completa de cada cliente registrado.
func (s *Server) Clients() []ClientInfo {
	s.mu.RLock()
	out := make([]ClientInfo, 0, len(s.clients))
	for _, c := range s.clients {
		out = append(out, c.info)
	}
	s.mu.RUnlock()
	sort.Slice(out, func(i, j int) bool { return out[i].Name < out[j].Name })
	return out
}

func (s *Server) registerClient(session *clientSession) {
	s.mu.Lock()
	s.clients[session.info.Name] = session
	count := len(s.clients)
	s.mu.Unlock()
	s.logger.Printf("[INFO] Client connected: %s (id=%s ip=%s) | Active clients: %d",
		session.info.Name, session.info.ID, session.info.RemoteIP, count)
}

func (s *Server) unregisterClient(session *clientSession) {
	s.mu.Lock()
	if current, ok := s.clients[session.info.Name]; ok && current == session {
		delete(s.clients, session.info.Name)
	}
	count := len(s.clients)
	s.mu.Unlock()
	s.logger.Printf("[INFO] Client disconnected: %s | Active clients: %d", session.info.Name, count)
}

func (s *Server) ListenAndServe() error {
	listener, err := net.Listen("tcp", s.address)
	if err != nil {
		return err
	}
	defer listener.Close()
	s.logger.Printf("[INFO] Listening control %s", s.address)

	if s.publicAddress != "" {
		publicListener, err := net.Listen("tcp", s.publicAddress)
		if err != nil {
			return err
		}
		s.logger.Printf("[INFO] Listening public %s", s.publicAddress)
		go s.acceptPublic(publicListener)
	}

	for {
		conn, err := listener.Accept()
		if err != nil {
			return err
		}
		go s.handleConnection(conn)
	}
}

func (s *Server) handleConnection(conn net.Conn) {
	defer conn.Close()

	dec := json.NewDecoder(bufio.NewReader(conn))

	// Fase 1 — handshake
	var hello Message
	_ = conn.SetReadDeadline(time.Now().Add(10 * time.Second))
	if err := dec.Decode(&hello); err != nil || hello.Type != MessageHello || hello.Name == "" {
		s.logger.Printf("[WARN] Handshake inválido desde %s", conn.RemoteAddr())
		return
	}
	_ = conn.SetReadDeadline(time.Time{})

	// Fase 4 — autenticación
	if s.authToken != "" && hello.Token != s.authToken {
		s.logger.Printf("[WARN] Token rechazado para cliente %s desde %s", hello.Name, conn.RemoteAddr())
		return
	}

	remoteIP, _, _ := net.SplitHostPort(conn.RemoteAddr().String())

	session := &clientSession{
		info: ClientInfo{
			ID:            hello.ID,
			Name:          hello.Name,
			RemoteIP:      remoteIP,
			ConnectedAt:   time.Now(),
			LastHeartbeat: time.Now(),
			Online:        true,
		},
		connection: conn,
		encoder:    json.NewEncoder(conn),
	}
	if session.info.ID == "" {
		session.info.ID = hello.Name
	}

	if err := session.send(Message{Type: MessageServerOK}); err != nil {
		return
	}

	s.registerClient(session)
	defer s.unregisterClient(session)

	// Fase 2 — heartbeat con deadline dinámico
	_ = conn.SetReadDeadline(time.Now().Add(s.heartbeatTimeout))

	for {
		var msg Message
		if err := dec.Decode(&msg); err != nil {
			s.logger.Printf("[WARN] Conexión perdida con %s: %v", session.info.Name, err)
			return
		}
		_ = conn.SetReadDeadline(time.Now().Add(s.heartbeatTimeout))

		switch msg.Type {
		case MessagePing:
			s.mu.Lock()
			session.info.LastHeartbeat = time.Now()
			s.mu.Unlock()
			s.logger.Printf("[DEBUG] Heartbeat: %s", session.info.Name)
			if err := session.send(Message{Type: MessagePong}); err != nil {
				return
			}
		case MessageData:
			s.forwardToPublic(msg.StreamID, msg.Payload)
		case MessageCloseStream:
			s.closeStream(msg.StreamID, session)
		case MessageClose:
			return
		}
	}
}

func (s *Server) acceptPublic(listener net.Listener) {
	defer listener.Close()
	for {
		conn, err := listener.Accept()
		if err != nil {
			return
		}
		go s.handlePublic(conn)
	}
}

func (s *Server) handlePublic(conn net.Conn) {
	client := s.chooseClient()
	if client == nil {
		s.logger.Printf("[WARN] Conexión pública rechazada: sin clientes online")
		conn.Close()
		return
	}

	id := strconv.FormatUint(atomic.AddUint64(&s.nextStreamID, 1), 10)
	s.mu.Lock()
	s.streams[id] = &stream{public: conn, client: client}
	s.mu.Unlock()

	s.logger.Printf("[DEBUG] Stream %s abierto hacia cliente %s", id, client.info.Name)

	if err := client.send(Message{Type: MessageOpenStream, StreamID: id}); err != nil {
		s.closeStream(id, client)
		return
	}

	defer s.closeStream(id, client)

	buffer := make([]byte, 32*1024)
	for {
		n, err := conn.Read(buffer)
		if n > 0 {
			payload := append([]byte(nil), buffer[:n]...)
			if sendErr := client.send(Message{Type: MessageData, StreamID: id, Payload: payload}); sendErr != nil {
				return
			}
		}
		if err != nil {
			return
		}
	}
}

func (s *Server) chooseClient() *clientSession {
	s.mu.RLock()
	defer s.mu.RUnlock()
	// Usa el cliente con nombre menor para selección determinista
	var chosen *clientSession
	for _, c := range s.clients {
		if chosen == nil || c.info.Name < chosen.info.Name {
			chosen = c
		}
	}
	return chosen
}

func (s *Server) forwardToPublic(id string, payload []byte) {
	s.mu.RLock()
	current := s.streams[id]
	s.mu.RUnlock()
	if current != nil && len(payload) > 0 {
		if _, err := current.public.Write(payload); err != nil {
			s.logger.Printf("[DEBUG] Error escribiendo stream %s: %v", id, err)
		}
	}
}

func (s *Server) closeStream(id string, client *clientSession) {
	s.mu.Lock()
	current, ok := s.streams[id]
	if ok && current.client == client {
		delete(s.streams, id)
	} else {
		ok = false
	}
	s.mu.Unlock()
	if !ok {
		return
	}
	_ = current.public.Close()
	_ = client.send(Message{Type: MessageCloseStream, StreamID: id})
	s.logger.Printf("[DEBUG] Stream %s cerrado", id)
}

// String formatea la info de cliente para logs externos.
func (ci ClientInfo) String() string {
	return fmt.Sprintf("%s (id=%s ip=%s online=%v last_hb=%s)",
		ci.Name, ci.ID, ci.RemoteIP, ci.Online, ci.LastHeartbeat.Format(time.RFC3339))
}
