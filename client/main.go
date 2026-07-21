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
	if serverURL := os.Getenv("PCC_SERVER_URL"); serverURL != "" {
		cfg.Server.URL = serverURL
	}
	if token := os.Getenv("PCC_AUTH_TOKEN"); token != "" {
		cfg.Client.Token = token
	}
	if registrationKey := os.Getenv("PCC_REGISTRATION_KEY"); registrationKey != "" {
		cfg.Client.RegistrationKey = registrationKey
	}
	if localService := os.Getenv("PCC_PROXY_LOCAL"); localService != "" {
		cfg.Proxy.Local = localService
	}

	logger, closeLog, err := newLogger(cfg.Log.File)
	if err != nil {
		logger = log.Default()
		closeLog = func() {}
	}
	defer closeLog()

	client, err := tunnel.NewClientWithLocalService(
		cfg.Server.URL, cfg.Client.ID, cfg.Client.Name,
		cfg.Client.Token, cfg.Client.RegistrationKey,
		cfg.Proxy.Local, cfg.SSL.Verify,
	)
	if err != nil {
		logger.Fatal(err)
	}

	reconnectDelay := time.Duration(cfg.Client.Reconnect) * time.Second
	heartbeatInterval := time.Duration(cfg.Client.Heartbeat) * time.Second

	for {
		session, err := client.Connect()
		if err != nil {
			logger.Printf("[WARN] Servidor HTTPS no disponible: %v; reintentando en %s", err, reconnectDelay)
			time.Sleep(reconnectDelay)
			if reconnectDelay < time.Minute {
				reconnectDelay *= 2
				if reconnectDelay > time.Minute {
					reconnectDelay = time.Minute
				}
			}
			continue
		}
		logger.Printf("[INFO] Cliente %s conectado al servidor HTTPS", cfg.Client.ID)
		reconnectDelay = time.Duration(cfg.Client.Reconnect) * time.Second
		err = session.RunHeartbeat(heartbeatInterval)
		_ = session.Close()
		logger.Printf("[WARN] Sesión finalizada: %v", err)
		time.Sleep(reconnectDelay)
	}
}
