package main

import (
	"fmt"
	"os"

	"gopkg.in/yaml.v3"
)

type gatewayConfig struct {
	Server gatewayServerConfig `yaml:"server"`
	Log    gatewayLogConfig    `yaml:"log"`
}

type gatewayServerConfig struct {
	ControlAddress   string `yaml:"control_address"`
	PublicAddress    string `yaml:"public_address"`
	AuthToken        string `yaml:"auth_token"`
	HeartbeatTimeout int    `yaml:"heartbeat_timeout"`
}

type gatewayLogConfig struct {
	Level string `yaml:"level"`
	File  string `yaml:"file"`
}

func loadGatewayConfig(path string) (*gatewayConfig, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("no se pudo leer configuración del gateway: %w", err)
	}

	cfg := &gatewayConfig{}
	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, fmt.Errorf("error leyendo configuración del gateway: %w", err)
	}
	if cfg.Server.ControlAddress == "" {
		cfg.Server.ControlAddress = ":8080"
	}
	if cfg.Server.PublicAddress == "" {
		cfg.Server.PublicAddress = ":8081"
	}
	if cfg.Server.HeartbeatTimeout <= 0 {
		cfg.Server.HeartbeatTimeout = 15
	}
	if cfg.Log.Level == "" {
		cfg.Log.Level = "info"
	}
	if cfg.Log.File == "" {
		cfg.Log.File = "logs/gateway.log"
	}
	return cfg, nil
}
