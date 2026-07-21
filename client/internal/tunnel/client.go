package tunnel

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"crypto/tls"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"sync"
	"time"
)

type Client struct {
	baseURL         string
	id              string
	name            string
	token           string
	registrationKey string
	localService    *url.URL
	httpClient      *http.Client
}

type Session struct {
	client *Client
}

type queuedRequest struct {
	RequestID string            `json:"request_id"`
	Method    string            `json:"method"`
	Path      string            `json:"path"`
	Headers   map[string]string `json:"headers"`
	Body      string            `json:"body"`
}

type tunnelResponse struct {
	RequestID  string            `json:"request_id"`
	StatusCode int               `json:"status_code"`
	Headers    map[string]string `json:"headers"`
	Body       string            `json:"body"`
}

func NewClientWithLocalService(serverURL, id, name, token, registrationKey, localService string, verifyTLS bool) (*Client, error) {
	baseURL := strings.TrimRight(serverURL, "/")
	parsedLocal, err := url.Parse(localService)
	if err != nil || parsedLocal.Scheme == "" || parsedLocal.Host == "" {
		return nil, fmt.Errorf("servicio local inválido: %q", localService)
	}
	transport := http.DefaultTransport.(*http.Transport).Clone()
	transport.TLSClientConfig = &tls.Config{InsecureSkipVerify: !verifyTLS} //nolint:gosec
	return &Client{
		baseURL: baseURL, id: id, name: name, token: token,
		registrationKey: registrationKey, localService: parsedLocal,
		httpClient: &http.Client{Timeout: 45 * time.Second, Transport: transport},
	}, nil
}

func (c *Client) Connect() (*Session, error) {
	if c.id == "" || c.name == "" || len(c.token) < 16 {
		return nil, fmt.Errorf("client.id, client.name y client.token son obligatorios")
	}
	if c.registrationKey != "" {
		payload, _ := json.Marshal(map[string]string{"client_id": c.id, "name": c.name, "token": c.token})
		req, err := http.NewRequest(http.MethodPost, c.endpoint("register.php"), bytes.NewReader(payload))
		if err != nil {
			return nil, err
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("X-PCC-Registration-Key", c.registrationKey)
		if _, err := c.do(req); err != nil {
			return nil, err
		}
	}
	if err := c.heartbeat(context.Background()); err != nil {
		return nil, err
	}
	return &Session{client: c}, nil
}

func (c *Client) endpoint(path string) string {
	return c.baseURL + "/" + path
}

func (c *Client) signedRequest(ctx context.Context, path string, body []byte) (*http.Request, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.endpoint(path), bytes.NewReader(body))
	if err != nil {
		return nil, err
	}
	timestamp := fmt.Sprintf("%d", time.Now().Unix())
	mac := hmac.New(sha256.New, []byte(c.token))
	_, _ = mac.Write([]byte(timestamp + "\n"))
	_, _ = mac.Write(body)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-PCC-Client-ID", c.id)
	req.Header.Set("X-PCC-Token", c.token)
	req.Header.Set("X-PCC-Timestamp", timestamp)
	req.Header.Set("X-PCC-Signature", hex.EncodeToString(mac.Sum(nil)))
	return req, nil
}

func (c *Client) heartbeat(ctx context.Context) error {
	req, err := c.signedRequest(ctx, "heartbeat.php", []byte("{}"))
	if err != nil {
		return err
	}
	_, err = c.do(req)
	return err
}

func (c *Client) poll(ctx context.Context) (*queuedRequest, error) {
	req, err := c.signedRequest(ctx, "poll.php", []byte("{}"))
	if err != nil {
		return nil, err
	}
	data, err := c.do(req)
	if err != nil || data == nil {
		return nil, err
	}
	var queued queuedRequest
	if err := json.Unmarshal(data, &queued); err != nil {
		return nil, err
	}
	return &queued, nil
}

func (c *Client) respond(ctx context.Context, payload tunnelResponse) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	req, err := c.signedRequest(ctx, "response.php", body)
	if err != nil {
		return err
	}
	_, err = c.do(req)
	return err
}

func (c *Client) do(req *http.Request) ([]byte, error) {
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(io.LimitReader(resp.Body, 20<<20))
	if err != nil {
		return nil, err
	}
	if resp.StatusCode == http.StatusNoContent {
		return nil, nil
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, fmt.Errorf("servidor respondió %d: %s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	return body, nil
}

func (s *Session) RunHeartbeat(interval time.Duration) error {
	if interval <= 0 {
		interval = 5 * time.Second
	}
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	errs := make(chan error, 1)
	var once sync.Once
	fail := func(err error) { once.Do(func() { errs <- err }) }
	go func() {
		ticker := time.NewTicker(interval)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				if err := s.client.heartbeat(ctx); err != nil {
					fail(err)
					return
				}
			}
		}
	}()
	for {
		select {
		case err := <-errs:
			return err
		default:
		}
		queued, err := s.client.poll(ctx)
		if err != nil {
			return err
		}
		if queued == nil {
			continue
		}
		if err := s.process(ctx, *queued); err != nil {
			return err
		}
	}
}

func (s *Session) process(ctx context.Context, queued queuedRequest) error {
	body, err := base64.StdEncoding.DecodeString(queued.Body)
	if err != nil {
		return s.client.respond(ctx, tunnelResponse{RequestID: queued.RequestID, StatusCode: http.StatusBadRequest})
	}
	target := *s.client.localService
	if parsed, err := url.Parse(queued.Path); err == nil {
		target.Path = parsed.Path
		target.RawQuery = parsed.RawQuery
	}
	req, err := http.NewRequestWithContext(ctx, queued.Method, target.String(), bytes.NewReader(body))
	if err != nil {
		return err
	}
	for name, value := range queued.Headers {
		if !hopByHopHeader(name) {
			req.Header.Set(name, value)
		}
	}
	resp, err := s.client.httpClient.Do(req)
	if err != nil {
		return s.client.respond(ctx, tunnelResponse{
			RequestID:  queued.RequestID,
			StatusCode: http.StatusBadGateway,
			Body:       base64.StdEncoding.EncodeToString([]byte(err.Error())),
		})
	}
	defer resp.Body.Close()
	respBody, err := io.ReadAll(io.LimitReader(resp.Body, 16<<20))
	if err != nil {
		return err
	}
	headers := make(map[string]string, len(resp.Header))
	for name, values := range resp.Header {
		if len(values) > 0 && !hopByHopHeader(name) {
			headers[name] = values[0]
		}
	}
	return s.client.respond(ctx, tunnelResponse{
		RequestID:  queued.RequestID,
		StatusCode: resp.StatusCode,
		Headers:    headers,
		Body:       base64.StdEncoding.EncodeToString(respBody),
	})
}

func hopByHopHeader(name string) bool {
	switch strings.ToLower(name) {
	case "connection", "content-length", "host", "keep-alive",
		"proxy-authenticate", "proxy-authorization", "te",
		"trailer", "transfer-encoding", "upgrade":
		return true
	default:
		return false
	}
}

func (s *Session) Close() error {
	return nil
}
