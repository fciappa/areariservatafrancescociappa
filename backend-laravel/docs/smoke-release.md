# Smoke Test Release API

Script rapido per verificare dopo deploy:

- envelope 404 standard
- envelope 422 standard
- login opzionale e accesso route protetta

## Script

- scripts/smoke-api.ps1

## Uso base

Da cartella backend-laravel:

```powershell
pwsh -File scripts/smoke-api.ps1 -BaseUrl "https://tuo-dominio"
```

## Abilitare check autenticazione

Imposta variabili ambiente nel terminale corrente:

```powershell
$env:SMOKE_USER = "admin_username"
$env:SMOKE_PASS = "admin_password"
pwsh -File scripts/smoke-api.ps1 -BaseUrl "https://tuo-dominio"
```

## Opzioni utili

- `-SkipAuthChecks` per saltare completamente i controlli login/protected
- `-VerboseOutput` per output diagnostico aggiuntivo in caso di failure

## Exit code

- `0` tutti i check passati
- `1` almeno un check fallito
