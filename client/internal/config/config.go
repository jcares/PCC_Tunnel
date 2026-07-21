package config

import (
	"fmt"
	"os"

	"gopkg.in/yaml.v3"
)

type Config struct {
	Server ServerConfig `yaml:"server"`
	Client ClientConfig `yaml:"client"`
	Proxy  ProxyConfig  `yaml:"proxy"`
	SSL    SSLConfig    `yaml:"ssl"`
	Log    LogConfig    `yaml:"log"`
}

type ServerConfig struct {
	URL string `yaml:"url"`
}

type ClientConfig struct {
	ID              string `yaml:"id"`
	Name            string `yaml:"name"`
	Token           string `yaml:"token"`
	RegistrationKey string `yaml:"registration_key"`
	Reconnect       int    `yaml:"reconnect"`
	Heartbeat       int    `yaml:"heartbeat"`
}

type ProxyConfig struct {
	Local string `yaml:"local"`
}

type SSLConfig struct {
	Verify bool `yaml:"verify"`
}

type LogConfig struct {
	Level string `yaml:"level"`
	File  string `yaml:"file"`
}

func Load(path string) (*Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("no se pudo leer config: %w", err)
	}
	cfg := &Config{}
	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, fmt.Errorf("error leyendo yaml: %w", err)
	}
	if cfg.Server.URL == "" {
		return nil, fmt.Errorf("server.url no definido")
	}
	if cfg.Client.ID == "" {
		cfg.Client.ID = cfg.Client.Name
	}
	if cfg.Client.Name == "" {
		return nil, fmt.Errorf("client.name no definido")
	}
	if cfg.Proxy.Local == "" {
		cfg.Proxy.Local = "http://127.0.0.1:80"
	}
	if cfg.Log.File == "" {
		cfg.Log.File = "logs/client.log"
	}
	if cfg.Client.Reconnect <= 0 {
		cfg.Client.Reconnect = 5
	}
	if cfg.Client.Heartbeat <= 0 {
		cfg.Client.Heartbeat = 5
	}
	return cfg, nil
}
