# Auto-register alias 'pod' for current PowerShell session
if (-not (Get-Alias pod -ErrorAction SilentlyContinue)) {
    Set-Alias -Name pod -Value "$PSCommandPath"
    Write-Host "Alias 'pod' registered. You can now use 'pod init', 'pod up', etc." -ForegroundColor Yellow
}

Param(
  [ValidateSet("init","up","down","restart","logs","ps","reset","seed","test")]
  [string]$cmd = "init"
)

function Run($c) {
  Write-Host ">> $c" -ForegroundColor Cyan
  cmd /c $c
  if ($LASTEXITCODE -ne 0) { throw "Command failed: $c" }
}

# ----------------------------------------------------------
# Usage:
#   pod init     - first full setup (build, deps, migrate, seed)
#   pod up       - build and start all containers
#   pod down     - stop and remove containers
#   pod restart  - restart all containers
#   pod logs     - show logs (tail + follow)
#   pod ps       - show container status
#   pod reset    - full reset (down -v + re-init)
#   pod seed     - reseed database
#   pod test     - run Laravel tests
# ----------------------------------------------------------

switch ($cmd) {
  "init" {
    Write-Host "=== Initializing full environment ===" -ForegroundColor Yellow

    # Copy base env files if missing
    if (-not (Test-Path ".env")) {
      Copy-Item ".env.example" ".env"
      Write-Host "Created root .env" -ForegroundColor DarkCyan
    }
    if (-not (Test-Path "backend/.env")) {
      Copy-Item ".env.example" "backend/.env"
      Write-Host "Created backend/.env" -ForegroundColor DarkCyan
    }
    if (-not (Test-Path "frontend/.env")) {
      Copy-Item ".env.example" "frontend/.env"
      Write-Host "Created frontend/.env" -ForegroundColor DarkCyan
    }

    # Build and start Docker containers
    Run "docker compose up -d --build"

    # Install dependencies (Composer + NPM)
    if (-not (Test-Path "backend/vendor")) {
      Run "docker compose exec app composer install"
    } else {
      Write-Host "Composer dependencies already installed — skipping." -ForegroundColor DarkGray
    }

    Run "docker compose exec app php artisan key:generate --ansi"

    # Fix Laravel storage permissions
    Run "docker compose exec app sh -lc 'mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache && chmod -R 777 storage bootstrap/cache'"

    # Migrate and seed DB
    Run "docker compose exec app php artisan migrate:fresh --seed"

    # Frontend setup
    if (-not (Test-Path "frontend/node_modules")) {
      Run "docker compose exec node sh -lc 'npm install'"
    } else {
      Write-Host "NPM modules already installed — skipping." -ForegroundColor DarkGray
    }

    Run "docker compose restart node"

    # Show container status
    Run "docker compose ps"

    Write-Host "`nEnvironment ready!" -ForegroundColor Green
    Write-Host "Backend → http://localhost:8081" -ForegroundColor Yellow
    Write-Host "Frontend → http://localhost:5173" -ForegroundColor Yellow
  }

  "up" {
    Run "docker compose up -d --build"
    Run "docker compose ps"
  }

  "down"     { Run "docker compose down" }
  "restart"  { Run "docker compose down"; Run "docker compose up -d" }
  "logs"     { Run "docker compose logs -f --tail=200" }
  "ps"       { Run "docker compose ps" }

  "reset" {
    Write-Host "Full reset: containers + Postgres volumes will be removed!" -ForegroundColor Yellow
    Run "docker compose down -v"
    & $PSCommandPath -cmd init
  }

  "seed" {
    Write-Host "Reseeding database..." -ForegroundColor Green
    Run "docker compose exec app php artisan migrate:fresh --seed"
  }

  "test" {
    Write-Host "Running Laravel tests..." -ForegroundColor Yellow
    Run "docker compose exec app php artisan test"
  }

  Default {
    Write-Host "Usage: pod [init|up|down|restart|logs|ps|reset|seed|test]" -ForegroundColor Magenta
  }
}
