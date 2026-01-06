SHELL := /bin/bash

.PHONY: init up down restart logs ps reset seed test

init: env up deps key storage migrate seed
	@echo "✅ Backend: http://localhost:8081  |  Frontend: http://localhost:5173"

# --- Env files ---
env:
	@test -f .env || cp .env.example .env
	@test -f backend/.env || cp backend/.env.example backend/.env
	@test -f frontend/.env || cp frontend/.env.example frontend/.env

# --- Docker ---
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

# --- Backend/Frontend deps ---
deps:
	@test -d backend/vendor || docker compose exec app composer install
	@test -d frontend/node_modules || docker compose exec node sh -lc 'npm install'
	docker compose restart node

# --- Laravel setup ---
key:
	@docker compose exec app sh -lc 'grep -q "^APP_KEY=base64:" .env || php artisan key:generate --ansi'

storage:
	docker compose exec app sh -lc 'mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache && chmod -R 777 storage bootstrap/cache'

migrate:
	docker compose exec app php artisan migrate --force

seed:
	docker compose exec app php artisan migrate:fresh --seed

test:
	docker compose exec app php artisan test
