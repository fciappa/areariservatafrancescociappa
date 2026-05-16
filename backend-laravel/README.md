# Area Riservata – Laravel API Backend

Backend PHP/Laravel che sostituisce il precedente backend Node.js/Express per la compatibilità con Hostinger shared hosting.

## Setup locale

### Requisiti
- PHP 8.1+
- Composer
- MySQL

### Installazione

```bash
cd backend-laravel
composer install
cp .env.example .env
# Modifica .env con le credenziali DB e i segreti JWT
php artisan serve
```

### Generare i segreti JWT

```bash
php -r "echo bin2hex(random_bytes(64));"
```

Inserisci due segreti diversi in `.env`:
- `JWT_SECRET` — per i token di accesso (15 min)
- `JWT_REFRESH_SECRET` — per i token di refresh (7 giorni)

## Deploy su Hostinger

### Struttura cartelle sul server
```
/home/u123456789/
├── domains/francescociappa.eu/
│   ├── areariservata/          ← cartella root Laravel (fuori public)
│   │   ├── app/
│   │   ├── bootstrap/
│   │   ├── config/
│   │   ├── routes/
│   │   ├── storage/
│   │   ├── vendor/
│   │   └── public/             ← DOCUMENT ROOT del subdomain
│   │       ├── index.php
│   │       ├── .htaccess
│   │       ├── index.html      ← Vue SPA (copiare da frontend/dist/)
│   │       └── assets/         ← Vue assets (copiare da frontend/dist/assets/)
```

### Passi deploy

1. **Prepara il vendor localmente:**
   ```bash
   cd backend-laravel
   composer install --optimize-autoloader --no-dev
   ```

2. **Build Vue frontend:**
   ```bash
   cd frontend
   npm run build
   ```

3. **Crea `.env` sul server** (non committare mai il `.env`):
   - Copia `.env.example` → `.env` nel backend-laravel
   - Inserisci credenziali DB e segreti JWT

4. **Carica via FTP:**
   - Tutto `backend-laravel/` → `areariservata/` (incluso `vendor/`)
   - Tutto `frontend/dist/` → `areariservata/public/`

5. **Imposta document root del subdomain** in Hostinger a:
   `areariservata/public/`

6. **Permessi storage** (via SSH o file manager):
   ```bash
   chmod -R 775 storage/
   chmod -R 775 bootstrap/cache/
   ```

### Nota importante su `APP_KEY`
Per Laravel, `APP_KEY` deve essere impostata. Generala con:
```bash
php artisan key:generate
```
Oppure genera manualmente: `base64:` + base64 di 32 byte random.
