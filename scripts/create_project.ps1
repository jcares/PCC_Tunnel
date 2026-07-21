# ==========================================================
# PCC_Tunnel - Development Project Reset Generator
# Ejecutar desde la raiz:
# D:\desarrollos\PCC_Tunnel
# ==========================================================

$ErrorActionPreference = "Stop"


Write-Host ""
Write-Host "======================================="
Write-Host " PCC_Tunnel Project Generator"
Write-Host "======================================="
Write-Host ""


# ----------------------------------------------------------
# Validar ubicación
# ----------------------------------------------------------

$current = Split-Path -Leaf (Get-Location)


if ($current -ne "PCC_Tunnel") {

    Write-Host "ERROR:"
    Write-Host "Ejecute este script desde la raiz PCC_Tunnel"
    exit 1

}


Write-Host "Ubicacion correcta:"
Get-Location



# ----------------------------------------------------------
# Limpiar proyecto actual
# ----------------------------------------------------------

Write-Host ""
Write-Host "Limpiando proyecto existente..."


Get-ChildItem -Force | Remove-Item -Recurse -Force



# ----------------------------------------------------------
# Crear estructura principal
# ----------------------------------------------------------

Write-Host "Creando estructura..."


$folders = @(

    "client",
    "client/config",
    "client/internal/config",
    "client/internal/tunnel",
    "client/internal/proxy",
    "client/internal/auth",
    "client/internal/logger",
    "client/internal/utils",
    "client/logs",

    "gateway",
    "gateway/tunnel",
    "gateway/config",
    "gateway/logs",

    "panel",
    "panel/api/v1",
    "panel/classes",
    "panel/config",
    "panel/storage/logs",

    "docs",

    "tests/client",
    "tests/server",

    "scripts",

    ".github/workflows"

)


foreach ($folder in $folders) {

    New-Item -ItemType Directory -Force $folder | Out-Null

}



# ==========================================================
# CLIENT GO
# ==========================================================


Write-Host "Creando cliente Go..."


@"
module github.com/pcc-tunnel/client

go 1.22

require (
    gopkg.in/yaml.v3 v3.0.1
)

"@ | Out-File client/go.mod -Encoding utf8



@"
package main


import (

    "fmt"
    "log"

    "github.com/pcc-tunnel/client/internal/config"

)



func main(){


    fmt.Println("===================================")
    fmt.Println(" PCC_Tunnel Client")
    fmt.Println("===================================")



    cfg,err:=config.Load(
        "config/config.yaml",
    )


    if err!=nil{

        log.Fatal(err)

    }



    fmt.Println("Servidor :",cfg.Server.URL)
    fmt.Println("Cliente  :",cfg.Client.Name)
    fmt.Println("Proxy    :",cfg.Proxy.Local)
    fmt.Println("SSL      :",cfg.SSL.Verify)



    fmt.Println("")
    fmt.Println("Cliente iniciado")

}

"@ | Out-File client/main.go -Encoding utf8



@"
package config


import (

    "fmt"
    "os"

    "gopkg.in/yaml.v3"

)



type Config struct {

    Server ServerConfig `yaml:"server"`
    Client ClientConfig `yaml:"client"`
    Proxy ProxyConfig `yaml:"proxy"`
    SSL SSLConfig `yaml:"ssl"`

}



type ServerConfig struct {

    URL string `yaml:"url"`

}



type ClientConfig struct {

    Name string `yaml:"name"`
    Token string `yaml:"token"`
    Reconnect int `yaml:"reconnect"`

}



type ProxyConfig struct {

    Local string `yaml:"local"`

}



type SSLConfig struct {

    Verify bool `yaml:"verify"`

}



func Load(path string)(*Config,error){


    data,err:=os.ReadFile(path)

    if err!=nil{

        return nil,err

    }



    cfg:=&Config{}



    err=yaml.Unmarshal(data,cfg)


    if err!=nil{

        return nil,fmt.Errorf(
            "error yaml: %v",
            err,
        )

    }



    if cfg.Server.URL==""{

        return nil,
        fmt.Errorf(
            "server.url no definido",
        )

    }



    if cfg.Proxy.Local==""{

        cfg.Proxy.Local=
        "http://127.0.0.1:80"

    }



    return cfg,nil

}

"@ | Out-File client/internal/config/config.go -Encoding utf8



@"
server:

  url: "tcp://127.0.0.1:8080"



client:

  name: "cliente-01"

  token: ""

  reconnect: 5



proxy:

  local: "http://127.0.0.1:80"



ssl:

  verify: false

"@ | Out-File client/config/config.yaml -Encoding utf8



# ==========================================================
# GATEWAY
# ==========================================================


Write-Host "Creando gateway..."


@"
module github.com/pcc-tunnel/gateway

go 1.22

"@ | Out-File gateway/go.mod -Encoding utf8



@"
package main


import (

    "fmt"
    "net"

)



func main(){


    listener,err:=net.Listen(
        "tcp",
        ":8080",
    )


    if err!=nil{

        panic(err)

    }



    fmt.Println(
        "PCC_Tunnel Gateway",
    )


    fmt.Println(
        "Listening :8080",
    )



    for{


        conn,err:=listener.Accept()


        if err!=nil{

            continue

        }



        fmt.Println(
            "Conexion:",
            conn.RemoteAddr(),
        )


    }

}

"@ | Out-File gateway/main.go -Encoding utf8



# ==========================================================
# PANEL PHP
# ==========================================================


Write-Host "Creando panel PHP..."


@"
<?php

echo "PCC_Tunnel Panel";

?>
"@ | Out-File panel/index.php -Encoding utf8



# ==========================================================
# DOCUMENTACION
# ==========================================================


"# PCC_Tunnel API" | Out-File docs/API.md
"# PCC_Tunnel Client" | Out-File docs/CLIENT.md
"# PCC_Tunnel Gateway" | Out-File docs/GATEWAY.md



"# PCC_Tunnel" | Out-File README.md

"" | Out-File LICENSE

"" | Out-File CHANGELOG.md

"" | Out-File CONTRIBUTING.md



Write-Host ""
Write-Host "======================================="
Write-Host " PCC_Tunnel regenerado correctamente"
Write-Host "======================================="
Write-Host ""

Write-Host "Siguiente:"
Write-Host ""
Write-Host "CLIENT:"
Write-Host "cd client"
Write-Host "go mod tidy"
Write-Host "go run ."
Write-Host ""

Write-Host "GATEWAY:"
Write-Host "cd gateway"
Write-Host "go run ."
