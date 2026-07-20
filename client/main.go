package main

import (
	"log"
	"os"
	"time"

	"github.com/pcc-tunnel/client/internal/config"
	"github.com/pcc-tunnel/client/internal/tunnel"
)

func main() {
	cfg, err := config.Load("config/config.yaml")
	if err != nil {
		log.Fatal(err)
	}
	if gatewayAddress := os.Getenv("PCC_GATEWAY_ADDR"); gatewayAddress != "" {
		cfg.Server.URL = gatewayAddress
	}

	logger, closeLog, err := newLogger(cfg.Log.File)
	if err != nil {
		log.Printf("No se pudo abrir log en %s, usando stdout: %v", cfg.Log.File, err)
		logger = log.Default()
		closeLog = func() {}
	}
	defer closeLog()

	logger.Printf("[INFO] PCC_Tunnel Client")
	logger.Printf("[INFO] Conectando a %s como %s (id=%s)", cfg.Server.URL, cfg.Client.Name, cfg.Client.ID)

	client := tunnel.NewClientWithLocalService(
		cfg.Server.URL,
		cfg.Client.ID,
		cfg.Client.Name,
		cfg.Client.Token,
		cfg.Proxy.Local,
	)

	baseReconnectDelay := time.Duration(cfg.Client.Reconnect) * time.Second
	heartbeatInterval := time.Duration(cfg.Client.Heartbeat) * time.Second
	if baseReconnectDelay <= 0 {
		baseReconnectDelay = 5 * time.Second
	}
	if heartbeatInterval <= 0 {
		heartbeatInterval = 5 * time.Second
	}

	reconnectDelay := baseReconnectDelay
	for {
		session, err := client.Connect()
		if err != nil {
			logger.Printf("[WARN] Gateway no disponible: %v — reintentando en %s", err, reconnectDelay)
			time.Sleep(reconnectDelay)
			if reconnectDelay < time.Minute {
				reconnectDelay *= 2
				if reconnectDelay > time.Minute {
					reconnectDelay = time.Minute
				}
			}
			continue
		}

		logger.Printf("[INFO] Connected Gateway")
		logger.Printf("[INFO] Client registered")
		reconnectDelay = baseReconnectDelay

		err = session.RunHeartbeat(heartbeatInterval)
		session.Close()
		logger.Printf("[WARN] Conexión perdida con Gateway: %v", err)
		time.Sleep(reconnectDelay)
	}
}
