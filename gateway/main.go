package main

import (
	"log"
	"os"
	"time"

	"github.com/pcc-tunnel/gateway/tunnel"
)

func main() {
	cfg, err := loadGatewayConfig("config/config.yaml")
	if err != nil {
		log.Printf("[WARN] No se pudo cargar config/config.yaml: %v — usando variables de entorno", err)
		cfg = &gatewayConfig{}
	}

	// Variables de entorno sobreescriben el YAML para despliegue en Docker.
	if v := os.Getenv("PCC_CONTROL_ADDR"); v != "" {
		cfg.Server.ControlAddress = v
	}
	if v := os.Getenv("PCC_PUBLIC_ADDR"); v != "" {
		cfg.Server.PublicAddress = v
	}
	if v := os.Getenv("PCC_AUTH_TOKEN"); v != "" {
		cfg.Server.AuthToken = v
	}
	if v := os.Getenv("PCC_LOG_FILE"); v != "" {
		cfg.Log.File = v
	}

	if cfg.Server.ControlAddress == "" {
		cfg.Server.ControlAddress = ":8080"
	}
	if cfg.Server.PublicAddress == "" {
		cfg.Server.PublicAddress = ":8081"
	}
	if cfg.Log.File == "" {
		cfg.Log.File = "logs/gateway.log"
	}

	logger, closeLog, err := newLogger(cfg.Log.File)
	if err != nil {
		log.Printf("[WARN] No se pudo abrir log en %s, usando stdout: %v", cfg.Log.File, err)
		logger = log.Default()
		closeLog = func() {}
	}
	defer closeLog()

	logger.Printf("[INFO] PCC_Tunnel Gateway")
	logger.Printf("[INFO] Control: %s | Publico: %s", cfg.Server.ControlAddress, cfg.Server.PublicAddress)

	server := tunnel.NewServerWithPublicAddress(cfg.Server.ControlAddress, cfg.Server.PublicAddress)
	server.SetAuthToken(cfg.Server.AuthToken)
	server.SetLogger(logger)
	if cfg.Server.HeartbeatTimeout > 0 {
		server.SetHeartbeatTimeout(time.Duration(cfg.Server.HeartbeatTimeout) * time.Second)
	}

	if err := server.ListenAndServe(); err != nil {
		logger.Fatalf("[ERROR] Gateway terminado: %v", err)
	}
}
