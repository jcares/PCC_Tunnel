package main

import (
	"fmt"
	"log"
	"os"
	"time"

	"github.com/pcc-tunnel/client/internal/config"
	"github.com/pcc-tunnel/client/internal/tunnel"
)

func main() {
	fmt.Println("PCC_Tunnel Client")

	cfg, err := config.Load("config/config.yaml")
	if err != nil {
		log.Fatal(err)
	}
	if gatewayAddress := os.Getenv("PCC_GATEWAY_ADDR"); gatewayAddress != "" {
		cfg.Server.URL = gatewayAddress
	}

	client := tunnel.NewClientWithLocalService(cfg.Server.URL, cfg.Client.Name, cfg.Client.Token, cfg.Proxy.Local)
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
			log.Printf("Gateway unavailable: %v", err)
			time.Sleep(reconnectDelay)
			if reconnectDelay < time.Minute {
				reconnectDelay *= 2
				if reconnectDelay > time.Minute {
					reconnectDelay = time.Minute
				}
			}
			continue
		}

		fmt.Println("Connected Gateway")
		fmt.Println("Client registered")
		reconnectDelay = baseReconnectDelay
		err = session.RunHeartbeat(heartbeatInterval)
		session.Close()
		log.Printf("Gateway connection lost: %v", err)
		time.Sleep(reconnectDelay)
	}
}
