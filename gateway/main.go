package main

import (
	"fmt"
	"log"
	"os"

	"github.com/pcc-tunnel/gateway/tunnel"
)

func main() {
	fmt.Println("PCC_Tunnel Gateway")

	controlAddress := os.Getenv("PCC_CONTROL_ADDR")
	if controlAddress == "" { controlAddress = ":8080" }
	publicAddress := os.Getenv("PCC_PUBLIC_ADDR")
	if publicAddress == "" { publicAddress = ":8081" }
	server := tunnel.NewServerWithPublicAddress(controlAddress, publicAddress)
	server.SetAuthToken(os.Getenv("PCC_AUTH_TOKEN"))
	if err := server.ListenAndServe(); err != nil {
		log.Fatal(err)
	}
}
