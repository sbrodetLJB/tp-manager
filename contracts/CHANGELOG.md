# Changelog du contrat API tpagent

## v1 (en cours)

- `GET /health` — vérification de disponibilité basique, sans authentification.
- `GET /v1/config` — configuration opérationnelle de l'agent (auth requise).
- `POST /v1/linux-accounts` — création idempotente d'un compte Linux restreint.
- `POST /v1/databases` — création idempotente d'une base + utilisateur scoppé (MySQL en Phase 2, PostgreSQL en Phase 3).
- `POST /v1/webroots` — création idempotente de l'arborescence de dépôt web d'un projet.

À venir (Phase 3+) : durcissement SFTP chroot, `DELETE` sur les trois ressources (Phase 5).
