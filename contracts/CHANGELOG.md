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

## v1.1

- `POST /v1/linux-accounts/{username}/reset-password` — change le secret
  d'authentification (mot de passe ou clé publique) d'un compte existant sans
  toucher au home ni aux fichiers déjà déposés par l'élève (404 si le compte
  n'existe pas).
- `POST /v1/databases/{dbName}/reset-password` — change le mot de passe de
  l'utilisateur BDD sans toucher à la base ni aux GRANT déjà accordés (404 si
  l'utilisateur n'existe pas).

Motivation : le seul moyen de récupérer un projet dont l'élève a perdu son mot
de passe était jusque-là déprovisionner puis reprovisionner, ce qui détruit
ses fichiers déposés et sa base de données. Ces deux endpoints permettent une
réinitialisation non destructive.
