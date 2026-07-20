package tunnel

const (
	MessageHello       = "HELLO"
	MessageServerOK    = "SERVER_OK"
	MessagePing        = "PING"
	MessagePong        = "PONG"
	MessageOpenStream  = "OPEN_STREAM"
	MessageData        = "DATA"
	MessageCloseStream = "CLOSE_STREAM"
	MessageClose       = "CLOSE"
)

type Message struct {
	Type     string `json:"type"`
	Name     string `json:"name,omitempty"`
	Token    string `json:"token,omitempty"`
	Data     string `json:"data,omitempty"`
	StreamID string `json:"stream_id,omitempty"`
	Payload  []byte `json:"payload,omitempty"`
}
