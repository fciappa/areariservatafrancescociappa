# Smoke Test Release API

Script rapido per verificare dopo deploy:

- envelope 404 standard
- envelope 422 standard
- flusso auth completo opzionale: login, refresh, route protetta, logout, invalidazione refresh token
- report Markdown automatico riusabile in release note

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

Se vuoi verificare una route protetta diversa da quella di default (`/api/users`):

```powershell
pwsh -File scripts/smoke-api.ps1 -BaseUrl "https://tuo-dominio" -ProtectedPath "/api/projects"
```

## Opzioni utili

- `-SkipAuthChecks` per saltare completamente i controlli login/protected
- `-ProtectedPath` per impostare la route protetta da verificare nel flusso auth
- `-ReportPath` percorso file report Markdown (default: `smoke-report.md`)
- `-VerboseOutput` per output diagnostico aggiuntivo in caso di failure

## Report Markdown

Esempio con percorso dedicato:

```powershell
pwsh -File scripts/smoke-api.ps1 -BaseUrl "https://tuo-dominio" -ReportPath "storage/logs/smoke-report.md"
```

Il report contiene:

- metadati esecuzione (timestamp, base URL, path protetta)
- esito complessivo
- tabella check `PASS/FAIL` con dettagli

## Artifact in GitHub Actions

Nel workflow CI backend il report viene pubblicato automaticamente come artifact `smoke-report`.

- Workflow: `.github/workflows/backend-ci.yml`
- Artifact: `smoke-report` (file `backend-laravel/storage/logs/smoke-report.md`)
- Pull Request: commento automatico con stato CI e link al run/artifact

## Exit code

- `0` tutti i check passati
- `1` almeno un check fallito
