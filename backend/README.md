# PoDrodze — Backend

Backend projektu PoDrodze (Laravel). Udostępnia REST API dla frontendu oraz integracje (m.in. Google Places, PostGIS).

## Wymagania (lokalnie)
- PHP 8.3+
- Composer
- PostgreSQL + PostGIS
- Redis

> Najprościej uruchamiać przez Docker (patrz README w root).

## Konfiguracja
W katalogu `backend/` skopiuj:
- `.env.example` → `.env`

Upewnij się, że masz ustawione m.in.:
- `APP_URL`
- `FRONTEND_URL`
- `DB_*`
- `REDIS_*`
- `GOOGLE_MAPS_KEY`
- `GOOGLE_CLIENT_ID / SECRET` (jeśli używasz logowania Google)

## Uruchomienie (Docker)
Z root projektu:

```bash
docker compose up --build
