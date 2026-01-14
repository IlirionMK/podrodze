# dev.ps1 — helper do PoDrodze (PowerShell)

# Always run from repo root
$ROOT = Split-Path -Parent $PSCommandPath
Push-Location $ROOT

# Auto-register alias 'pod' for current PowerShell session
if (-not (Get-Alias pod -ErrorAction SilentlyContinue)) {
    Set-Alias -Name pod -Value $PSCommandPath
    Write-Host "Alias 'pod' registered. You can now use: pod init | pod up | pod down | ..." -ForegroundColor Yellow
}

Param(
  [ValidateSet("init","up","down","restart","logs","ps","reset","seed","test")]
  [string]$cmd = "init"
)

function Run([string]$exe, [string[]]$args) {
  $line = $exe + " " + ($args -join " ")
  Write-Host ">> $line" -ForegroundColor Cyan
  & $exe @args
  if ($LASTEXITCODE -ne 0) { throw "Command failed: $line" }
}

function EnsureEnv([string]$dir) {
  $envPath = Join-Path $dir ".env"
  $examplePath = Join-Path $dir ".env.example"

  if (-not (Test-Path $examplePath)) {
    Write-Host "Missing $examplePath (skipping)" -ForegroundColor DarkGray
    return
  }

  if (-not (Test-Path $envPath)) {
    Copy-Item $examplePath $envPath
    Write-Host "Created $envPath" -ForegroundColor DarkCyan
  }
}

switch ($cmd) {
  "init" {
    Write-Host "=== Initializing environment ===" -ForegroundColor Yellow

    # Create env files if missing (from correct directories)
    EnsureEnv "."
    EnsureEnv "backend"
    EnsureEnv "frontend"

    # Build and start Docker containers
    Run "docker" @("compose","up","-d","--build")

    # Backend deps
    if (-not (Test-Path "backend/vendor")) {
      Run "docker" @("compose","exec","app","composer","install")
    }

    # Generate APP_KEY only if empty
    try {
      $backendEnv = Get-Content "backend/.env" -ErrorAction Stop
      $hasKey = $backendEnv | Where-Object { $_ -match '^APP_KEY=base64:' }
      if (-not $hasKey) {
        Run "docker" @("compose","exec","app","php","artisan","key:generate","--ansi")
      } else {
        Write-Host "APP_KEY already set — skipping key:generate." -ForegroundColor DarkGray
      }
    } catch {
      # If we can't read env, still try to generate key
      Run "docker" @("compose","exec","app","php","artisan","key:generate","--ansi")
    }

    # Storage/cache dirs (dev-friendly perms)
    Run "docker" @("compose","exec","app","sh","-lc","mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache && chmod -R 777 storage bootstrap/cache")

    # Migrate + seed
    Run "docker" @("compose","exec","app","php","artisan","migrate:fresh","--seed")

    # Frontend deps
    if (-not (Test-Path "frontend/node_modules")) {
      Run "docker" @("compose","exec","node","sh","-lc","npm install")
    }

    # Show container status
    Run "docker" @("compose","ps")

    Write-Host "`nEnvironment ready!" -ForegroundColor Green
    Write-Host "Backend  → http://localhost:8081" -ForegroundColor Yellow
    Write-Host "Frontend → http://localhost:5173" -ForegroundColor Yellow
  }

  "up" {
    Run "docker" @("compose","up","-d","--build")
    Run "docker" @("compose","ps")
  }

  "down"    { Run "docker" @("compose","down") }
  "restart" { Run "docker" @("compose","down"); Run "docker" @("compose","up","-d") }
  "logs"    { Run "docker" @("compose","logs","-f","--tail=200") }
  "ps"      { Run "docker" @("compose","ps") }

  "reset" {
    Write-Host "Full reset: containers + volumes will be removed!" -ForegroundColor Yellow
    Run "docker" @("compose","down","-v")
    & $PSCommandPath -cmd init
  }

  "seed" {
    Write-Host "Reseeding database..." -ForegroundColor Green
    Run "docker" @("compose","exec","app","php","artisan","migrate:fresh","--seed")
  }

  "test" {
    Write-Host "Running Laravel tests..." -ForegroundColor Yellow
    Run "docker" @("compose","exec","app","php","artisan","test")
  }

  Default {
    Write-Host "Usage: pod [init|up|down|restart|logs|ps|reset|seed|test]" -ForegroundColor Magenta
  }
}

Pop-Location
