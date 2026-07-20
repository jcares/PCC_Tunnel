package main

import (
	"io"
	"log"
	"os"
	"path/filepath"
)

func newLogger(path string) (*log.Logger, func(), error) {
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return nil, nil, err
	}
	file, err := os.OpenFile(path, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		return nil, nil, err
	}
	return log.New(io.MultiWriter(os.Stdout, file), "", log.Ldate|log.Ltime|log.LUTC), func() { _ = file.Close() }, nil
}
