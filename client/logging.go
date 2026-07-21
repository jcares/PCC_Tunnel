package main

import (
	"io"
	"log"
	"os"
	"path/filepath"
)

func newLogger(file string) (*log.Logger, func(), error) {
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		return nil, nil, err
	}
	f, err := os.OpenFile(file, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644)
	if err != nil {
		return nil, nil, err
	}
	logger := log.New(io.MultiWriter(os.Stdout, f), "", log.LstdFlags|log.Lmsgprefix)
	return logger, func() { _ = f.Close() }, nil
}
