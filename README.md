# Area Riservata - Francesco Ciappa

Applicazione web personale per la gestione di collaboratori, clienti, tariffe, ore e fatture.

## Stack
- **Frontend**: Vue 3 + Vite + Vue Router + Pinia
- **Backend**: PHP 8.1+ / Laravel 11
- **Database**: MySQL 8+
- **Auth**: JWT (access token + refresh token)

## Struttura

```
areariservata/
├── backend-laravel/  # API Laravel (PHP 8.1+)
├── frontend/         # App Vue 3 + Vite
├── database/         # Schema MySQL e migrations
├── initialize.bat    # Setup prima installazione
└── start.bat         # Avvio ambiente di sviluppo
```

## Prima installazione (nuova macchina)

### Prerequisiti
- PHP 8.1+ — https://www.php.net/downloads
- Composer — https://getcomposer.org
- Node.js — https://nodejs.org
- MySQL 8+ — https://dev.mysql.com/downloads

### Avvio guidato
```
initialize.bat
```
Lo script si occupa di: creare il database, importare lo schema, copiare il `.env`, installare le dipendenze PHP (Composer) e le dipendenze frontend (npm).

Dopo `initialize.bat`, apri `backend-laravel\.env` e verifica le credenziali MySQL.

## Avvio sviluppo

```
start.bat
```

Apre due terminali (Laravel su `http://localhost:8000`, Vite su `http://localhost:5173`) e il browser.

## Credenziali default
- **Admin**: `admin` / `Admin@2024`
- ⚠️ Cambiare la password al primo accesso!
