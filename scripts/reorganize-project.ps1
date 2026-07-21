[CmdletBinding()]
param(
    [string]$ProjectRoot
)

$ErrorActionPreference = 'Stop'
$ScriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $ProjectRoot = Split-Path -Parent $ScriptDirectory
}
Set-Location -LiteralPath $ProjectRoot

function Ensure-Directory([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Move-Directory([string]$Source, [string]$Destination) {
    if (Test-Path -LiteralPath $Source -PathType Container) {
        Ensure-Directory (Split-Path -Parent $Destination)
        if (-not (Test-Path -LiteralPath $Destination -PathType Container)) {
            Move-Item -LiteralPath $Source -Destination $Destination
        }
    }
}

function Move-File([string]$Source, [string]$Destination) {
    if (Test-Path -LiteralPath $Source -PathType Leaf) {
        Ensure-Directory (Split-Path -Parent $Destination)
        if (-not (Test-Path -LiteralPath $Destination -PathType Leaf)) {
            Move-Item -LiteralPath $Source -Destination $Destination
        }
    }
}

function Remove-Residual([string]$Path) {
    if (Test-Path -LiteralPath $Path) {
        Remove-Item -LiteralPath $Path -Recurse -Force
    }
}

Ensure-Directory 'client'
Ensure-Directory 'server'
Ensure-Directory 'docs'
Ensure-Directory 'tests'
Ensure-Directory 'scripts'
Ensure-Directory 'deploy/cpanel'
Ensure-Directory 'deploy/casaos'

foreach ($directory in @('api', 'Classes', 'database', 'panel')) {
    Move-Directory $directory "server/$directory"
}

Move-File 'server.env.example' 'server/server.env.example'

foreach ($file in @('test_forwarding.ps1', 'test_handshake.ps1', 'test_heartbeat.ps1', 'test_sessions.ps1')) {
    Move-File $file "tests/$file"
}

foreach ($file in @('build-local-images.sh', 'create_project.ps1', 'publish-images.ps1')) {
    Move-File $file "scripts/$file"
}

foreach ($file in @('CLIENT.md', 'SERVER.md', 'INSTALL.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'ROADMAP.md')) {
    if (Test-Path -LiteralPath $file -PathType Leaf) {
        Move-File $file "docs/$file"
    }
}

if (Test-Path -LiteralPath 'API.md' -PathType Leaf) {
    Move-File 'API.md' 'docs/API_REFERENCE.md'
}

foreach ($path in @(
    'gateway',
    'docker-compose.yml',
    'docker-compose.ghcr.yml',
    'client/Dockerfile',
    'client/pcc-client.exe',
    'client/pcc-client',
    'client/logs',
    'client-sessions-error.log',
    'client-sessions-test.log',
    'gateway-sessions-error.log',
    'gateway-sessions-test.log',
    'deploy-compose-placeholder.yml',
    'deploy-compose-override-placeholder.yml',
    'casaos.env.example',
    'casaos.config.yaml'
)) {
    Remove-Residual $path
}

Get-ChildItem -Path $ProjectRoot -File -Filter '*.log' -Force | Remove-Item -Force

@'
Options -Indexes

<FilesMatch "^\.env$">
    Require all denied
</FilesMatch>

<FilesMatch "\.(sql|log|yaml|yml)$">
    Require all denied
</FilesMatch>

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^database/ - [F,L]
</IfModule>
'@ | Set-Content -LiteralPath 'server/.htaccess' -Encoding utf8

@'
<?php
declare(strict_types=1);

header('Location: panel/', true, 302);
exit;
'@ | Set-Content -LiteralPath 'server/index.php' -Encoding utf8

@'
PCC_DB_HOST=127.0.0.1
PCC_DB_NAME=pcc_tunnel
PCC_DB_USER=cpanel_database_user
PCC_DB_PASS=change-this-password
PCC_REGISTRATION_KEY=change-this-registration-key
'@ | Set-Content -LiteralPath 'server/server.env.example' -Encoding utf8

& (Join-Path $ScriptDirectory 'package.ps1') -ProjectRoot $ProjectRoot
Write-Host "Reorganización y empaquetado completados en: $ProjectRoot" -ForegroundColor Green
