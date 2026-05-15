# Area Riservata - Francesco Ciappa

Applicazione web personale per la gestione di collaboratori, clienti, tariffe, ore e fatture.

## Stack
- **Frontend**: Vue 3 + Vite + Vue Router + Pinia
- **Backend**: Node.js + Express
- **Database**: MySQL 8+
- **Auth**: JWT (access token + refresh token)

## Struttura

```
areariservata/
├── backend/          # API Node.js + Express
├── frontend/         # App Vue 3 + Vite
├── database.sql      # Schema MySQL
└── package.json      # Monorepo root
```

## Setup

### 1. Database
```bash
mysql -u root -p < database.sql
```

### 2. Backend
```bash
cd backend
cp .env.example .env
# Modifica .env con i tuoi dati
npm install
npm run dev
```

### 3. Frontend
```bash
cd frontend
npm install
npm run dev
```

### 4. Avvio completo (dalla root)
```bash
npm install
npm run dev
```

## Credenziali default
- **Admin**: `admin` / `Admin@2024`
- ⚠️ Cambiare la password al primo accesso!
