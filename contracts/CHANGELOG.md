# Changelog du contrat API tpagent

## v1

- `GET /health` — vérification de disponibilité basique, sans authentification.
- `GET /v1/config` — configuration opérationnelle de l'agent (auth requise).
- `POST /v1/linux-accounts` — création idempotente d'un compte Linux restreint.
- `POST /v1/databases` — création idempotente d'une base + utilisateur scoppé (MySQL et PostgreSQL).
- `POST /v1/webroots` — création idempotente de l'arborescence de dépôt web d'un projet.
- `DELETE /v1/linux-accounts/{username}` — suppression idempotente (404 si déjà absent = no-op côté client).
- `DELETE /v1/databases/{dbName}` — suppression idempotente de la base + utilisateur associé.
- `DELETE /v1/webroots` (corps `{eleveLogin, projetSlug}`) — suppression du seul sous-dossier projet, jamais la racine élève.

Le contrat est stable depuis la Phase 5 (déprovisioning). Toute évolution
future se fait d'abord dans `openapi.yaml`, puis côté client
(`dashboard/src/Service/Agent`) et serveur (`agent/src/tpagent/api`).
