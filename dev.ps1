Param(
  [ValidateSet("init","up","down","restart","logs","ps","reset")]
  [string]$cmd = "init"
)

function Run($c) {
  Write-Host ">> $c" -ForegroundColor Cyan
  cmd /c $c
  if ($LASTEXITCODE -ne 0) { throw "Команда упала: $c" }
}

switch ($cmd) {
  "init" {
    Run "docker compose up -d --build"
    Run "docker compose exec app composer install"
    Run "docker compose exec app php artisan key:generate"
    Run "docker compose exec app php artisan migrate --force"
    # права для Laravel (важно для Windows/Docker)
    Run "docker compose exec app sh -lc 'mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache && chmod -R 777 storage bootstrap/cache'"
    # фронт
    Run "docker compose exec node sh -lc 'npm install'"
    Run "docker compose restart node"
    # показать статусы
    Run "docker compose ps"
    Write-Host "`n✅ Среда готова: Backend → http://localhost:8081, Frontend → http://localhost:5173" -ForegroundColor Green
  }

  "up"       { Run "docker compose up -d --build"; Run "docker compose ps" }
  "down"     { Run "docker compose down" }
  "restart"  { Run "docker compose down"; Run "docker compose up -d" }
  "logs"     { Run "docker compose logs -f --tail=200" }
  "ps"       { Run "docker compose ps" }
  "reset"    {
    Write-Host "⚠️ Полный сброс контейнеров и данных Postgres (volume)!" -ForegroundColor Yellow
    Run "docker compose down -v"
    Run "docker compose up -d --build"
    Run "docker compose exec app composer install"
    Run "docker compose exec app php artisan key:generate"
    Run "docker compose exec app php artisan migrate --force"
    Run "docker compose exec app sh -lc 'mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache && chmod -R 777 storage bootstrap/cache'"
    Run "docker compose exec node sh -lc 'npm install'"
    Run "docker compose restart node"
    Run "docker compose ps"
  }
}
