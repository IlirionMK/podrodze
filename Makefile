SHELL := /bin/bash

.PHONY: init up down restart logs ps reset migrate cache

init: up
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --force
	docker compose exec app sh -lc 'mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache && chmod -R 777 storage bootstrap/cache'
	docker compose exec node sh -lc 'npm install'
	docker compose restart node
	@echo "✅ Backend: http://localhost:8081  |  Frontend: http://localhost:5173"

up:
	docker compose up -d --build
	docker compose ps

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d

logs:
	docker compose logs -f --tail=200

ps:
	docker compose ps

reset:
	docker compose down -v
	$(MAKE) init
