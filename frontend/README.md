# PoDrodze — Frontend

Frontend (Vue 3 / Vite). Komunikuje się z backendem przez REST API.

## .env
Skopiuj `.env.example` → `.env` i ustaw:
```env
VITE_API_BASE_URL=http://localhost:8081/api/v1
VITE_MAPS_KEY=YOUR_GOOGLE_MAPS_KEY_HERE
```

## Instalacja
```bash
npm install
npm run dev
```

## Testowanie

### Testy jednostkowe komponentów (Vitest + Vue Test Utils)
```bash
# Uruchom testy w trybie watch
npm run test

# Uruchom testy raz
npm run test:run

# Uruchom interfejs graficzny
npm run test:ui
```

### Testy E2E (Playwright)
```bash
# Zainstaluj przeglądarki Playwright
npm run test:e2e:install

# Uruchom testy E2E
npm run test:e2e

# Uruchom testy E2E z interfejsem graficznym
npm run test:e2e:ui
```

### Struktura testów
```
tests/
├── unit/           # Testy jednostkowe komponentów Vue
│   └── components/
│       ├── Header.test.js
│       ├── Footer.test.js
│       └── LanguageSwitcher.test.js
├── integration/    # Testy integracyjne API i composables
│   ├── api.test.js
│   └── composables.test.js
└── e2e/           # Testy end-to-end (Playwright)
    ├── basic-navigation.spec.js
    ├── auth-flow.spec.js
    └── accessibility.spec.js
```

## Pokrycie testami

### ✅ Zaimplementowane:
- **Testy jednostkowe komponentów Vue** (Header, Footer, LanguageSwitcher)
- **Testy E2E** (nawigacja, flow autentykacji, dostępność)
- **Testy integracyjne API** (mockowanie zapytań HTTP)
- **Testy composables** (useAuth, useTrips, i18n)

### 🔧 Konfiguracja:
- **Vitest** - framework testów jednostkowych
- **Vue Test Utils** - biblioteka do testowania komponentów Vue
- **Playwright** - framework testów E2E
- **jsdom** - środowisko DOM dla testów jednostkowych
