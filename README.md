
---

# **PoDrodze — aplikacjja do planowania podróży**

## **Opis projektu**

PoDrodze to aplikacja webowa do **wspólnego tworzenia i zarządzania planem podróży**.
Użytkownicy mogą dodawać uczestników, określać preferencje, wybierać miejsca, głosować oraz generować plan podróży z wykorzystaniem AI i danych przestrzennych **PostGIS**.

---

## **Wymagania funkcjonalne (MVP)**

### **Użytkownicy i autoryzacja**

* Rejestracja i logowanie
* Tokenowa autoryzacja
* Profil użytkownika

### **Plan podróży**

* Tworzenie nowego planu podróży
* Edycja i usuwanie planu
* Zapraszanie uczestników do planu podróży
* Akceptacja / odrzucanie zaproszeń
* Role i statusy uczestników

### **Miejsca**

* Pobieranie miejsc z **Google Places API**
* Przechowywanie lokalizacji w formacie **PostGIS geometry**
* Kategorie miejsc
* Dodawanie lub usuwanie miejsc w planie podróży
* Głosowanie uczestników na proponowane miejsca

### **Preferencje i AI**

* Preferencje kategorii użytkowników
* Agregacja preferencji całej grupy
* Rekomendacje AI 
* Sugestie ulepszeń planu podróży

### **Itinerarz**

* Automatyczne generowanie **planu podróży**
* Podział na dni i kolejność miejsc
* Edycja planu przez użytkownika

---

## **Architektura**

### **Backend (Laravel)**

* REST API 
* Warstwy:

    * **Controllers** – obsługa żądań
    * **Services** – logika
    * **Models** – operacje na danych
* Integracja z Google Places
* Obliczenia geolokalizacyjne realizowane przez **PostGIS**
* Dokumentacja API (Scribe / OpenAPI)

### **Frontend (Vue 3)**

* SPA
* Widoki: logowanie, lista planów podróży, szczegóły planu, rekomendacje AI
* Integracja z API poprzez Axios

### **Baza danych**

* **PostgreSQL + PostGIS**
* Kluczowe tabele:

    * `users`
    * `trips` 
    * `places`
    * `preferences`
* Kolumny geometryczne dla lokalizacji miejsc

### **Infrastruktura**

* Docker 
* Kontenery: PHP-FPM, Node, PostGIS, Caddy, Mailpit.
* Gotowe środowisko dev

---

## **Technologie**

* PHP 8.3
* Laravel
* PostgreSQL + PostGIS
* Vue 3
* Redis
* Docker
* Google Places API

## **Uruchomienie**

### **1. Klonowanie repozytorium**

```bash
git clone https://github.com/IlirionMK/podrodze
cd podrodze
```

### **2. Przygotowanie plików konfiguracyjnych**

W katalogach `backend` i `frontend` skopiuj pliki `.env.example` do `.env`.

### **3. Start kontenerów**

```bash
docker compose up --build
```

### **4. Backend — migracje i seedery**

```bash
docker compose exec app bash
php artisan migrate --seed
exit
```

### **5. Frontend — instalacja zależności**

```bash
docker compose exec node bash
npm install
npm run dev
exit
```

---


