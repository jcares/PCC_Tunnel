package tunnel

// StatusPending y sus variantes representan el ciclo de vida de una solicitud en la cola.
const (
	StatusPending    = "pending"
	StatusProcessing = "processing"
	StatusCompleted  = "completed"
	StatusExpired    = "expired"
)
