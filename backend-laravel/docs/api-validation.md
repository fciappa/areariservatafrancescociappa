# Validazione API Centralizzata

Questo documento descrive dove sono definite le regole di validazione API, come vengono applicate e quale formato errori aspettarsi lato frontend.

## Componenti principali

- Regole centrali: App\Support\ApiValidationRules
- Esecutore validazione: App\Support\ApiRequestValidator
- Envelope errori API: bootstrap/app.php (handler eccezioni)

## Formato errori

### Errori di validazione (422)

```json
{
  "message": "Dati non validi",
  "errors": {
    "field": ["messaggio errore"]
  }
}
```

### Errori non 422 (standardizzati)

```json
{
  "message": "Risorsa non trovata | Conflitto dati | Errore interno del server | ...",
  "error": "NomeEccezione"
}
```

## Mappatura endpoint -> regole

### AuthController

- POST /api/auth/login -> ApiValidationRules::authLogin
- POST /api/auth/refresh -> ApiValidationRules::authRefresh

### ClientsController

- POST /api/clients -> ApiValidationRules::clientStore
- PUT /api/clients/{id} -> ApiValidationRules::clientUpdate($id)
- POST /api/clients/{id}/referents -> ApiValidationRules::clientAddReferents

### ProjectsController

- POST /api/projects -> ApiValidationRules::projectStore
- PUT /api/projects/{id} -> ApiValidationRules::projectUpdate
- GET /api/projects/tariff/resolve -> ApiValidationRules::projectResolveTargetTariff

Note: addAssignment e addReferent usano validazione centralizzata inline con ApiRequestValidator.

### DeadlinesController

- POST /api/deadlines -> ApiValidationRules::deadlineStore
- PUT /api/deadlines/{id} -> ApiValidationRules::deadlineUpdate

### ReferentsController

- POST /api/referents -> ApiValidationRules::referentStore
- PUT /api/referents/{id} -> ApiValidationRules::referentUpdate($id)

### UsersController

- POST /api/users -> ApiValidationRules::userStore
- PUT /api/users/{id}/password -> ApiValidationRules::userChangePassword

## Test automatici collegati

- tests/Unit/ApiRequestValidatorTest.php
- tests/Feature/ApiValidationErrorFormatTest.php
- tests/Feature/ApiEndpointValidationCoverageTest.php

## Linee guida operative

- Nuovi endpoint write (POST/PUT/PATCH) devono usare ApiRequestValidator.
- Nuove regole vanno aggiunte in ApiValidationRules, non hardcoded nel controller.
- Se una risposta errore custom diverge dall'envelope standard, documentare esplicitamente il motivo.
