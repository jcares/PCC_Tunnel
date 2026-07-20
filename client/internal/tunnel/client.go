package tunnel

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net"
	"net/url"
	"strings"
	"sync"
	"time"
)

type Client struct {
	serverURL    string
	id           string
	name         string
	token        string
	localService string
}

type Session struct {
	connection   net.Conn
	encoder      *json.Encoder
	decoder      *json.Decoder
	writeMu      sync.Mutex
	streamsMu    sync.RWMutex
	streams      map[string]net.Conn
	localService string
}

func NewClient(serverURL, id, name, token string) *Client {
	return &Client{serverURL: strings.TrimPrefix(serverURL, "tcp://"), id: id, name: name, token: token}
}

func NewClientWithLocalService(serverURL, id, name, token, localService string) *Client {
	client := NewClient(serverURL, id, name, token)
	client.localService = localService
	return client
}

func (c *Client) Connect() (*Session, error) {
	connection, err := net.Dial("tcp", c.serverURL)
	if err != nil {
		return nil, err
	}
	session := &Session{
		connection:   connection,
		encoder:      json.NewEncoder(connection),
		decoder:      json.NewDecoder(bufio.NewReader(connection)),
		streams:      make(map[string]net.Conn),
		localService: c.localService,
	}
	if err := session.handshake(c.id, c.name, c.token); err != nil {
		connection.Close()
		return nil, err
	}
	return session, nil
}

func (s *Session) send(message Message) error {
	s.writeMu.Lock()
	defer s.writeMu.Unlock()
	return s.encoder.Encode(message)
}

func (s *Session) handshake(id, name, token string) error {
	if err := s.send(Message{Type: MessageHello, ID: id, Name: name, Token: token}); err != nil {
		return err
	}
	var response Message
	if err := s.decoder.Decode(&response); err != nil {
		return err
	}
	if response.Type != MessageServerOK {
		return fmt.Errorf("respuesta inesperada del Gateway: %s", response.Type)
	}
	return nil
}

// RunHeartbeat mantiene la sesión activa y despacha tráfico de túnel.
func (s *Session) RunHeartbeat(interval time.Duration) error {
	if interval <= 0 {
		interval = time.Second
	}
	readErr := make(chan error, 1)
	go func() { readErr <- s.readLoop() }()
	ticker := time.NewTicker(interval)
	defer ticker.Stop()
	for {
		select {
		case <-ticker.C:
			if err := s.connection.SetReadDeadline(time.Now().Add(interval * 2)); err != nil {
				return err
			}
			if err := s.send(Message{Type: MessagePing}); err != nil {
				return err
			}
		case err := <-readErr:
			return err
		}
	}
}

func (s *Session) readLoop() error {
	for {
		var message Message
		if err := s.decoder.Decode(&message); err != nil {
			return err
		}
		switch message.Type {
		case MessagePong:
		case MessageOpenStream:
			s.openLocalStream(message.StreamID)
		case MessageData:
			s.streamsMu.RLock()
			local := s.streams[message.StreamID]
			s.streamsMu.RUnlock()
			if local == nil {
				continue
			}
			if _, err := local.Write(message.Payload); err != nil {
				s.closeLocalStream(message.StreamID, true)
			}
		case MessageCloseStream:
			s.closeLocalStream(message.StreamID, false)
		case MessageClose:
			return net.ErrClosed
		}
	}
}

func (s *Session) openLocalStream(id string) {
	if id == "" || s.localService == "" {
		_ = s.send(Message{Type: MessageCloseStream, StreamID: id})
		return
	}
	address, err := localAddress(s.localService)
	if err != nil {
		_ = s.send(Message{Type: MessageCloseStream, StreamID: id})
		return
	}
	local, err := net.Dial("tcp", address)
	if err != nil {
		_ = s.send(Message{Type: MessageCloseStream, StreamID: id})
		return
	}
	s.streamsMu.Lock()
	s.streams[id] = local
	s.streamsMu.Unlock()
	go func() {
		buffer := make([]byte, 32*1024)
		for {
			n, readErr := local.Read(buffer)
			if n > 0 {
				payload := append([]byte(nil), buffer[:n]...)
				if err := s.send(Message{Type: MessageData, StreamID: id, Payload: payload}); err != nil {
					break
				}
			}
			if readErr != nil {
				break
			}
		}
		s.closeLocalStream(id, true)
	}()
}

func (s *Session) closeLocalStream(id string, notify bool) {
	s.streamsMu.Lock()
	local, ok := s.streams[id]
	if ok {
		delete(s.streams, id)
	}
	s.streamsMu.Unlock()
	if ok {
		_ = local.Close()
	}
	if notify {
		_ = s.send(Message{Type: MessageCloseStream, StreamID: id})
	}
}

func localAddress(value string) (string, error) {
	if !strings.Contains(value, "://") {
		return value, nil
	}
	parsed, err := url.Parse(value)
	if err != nil || parsed.Host == "" {
		return "", fmt.Errorf("servicio local inválido: %q", value)
	}
	return parsed.Host, nil
}

func (s *Session) Close() error {
	s.streamsMu.Lock()
	for id, local := range s.streams {
		_ = local.Close()
		delete(s.streams, id)
	}
	s.streamsMu.Unlock()
	return s.connection.Close()
}
