# TP Manager

Outil pour les enseignants d'informatique (BTS SIO SLAM) qui gèrent un serveur
de TP sur une VM Linux : configuration du serveur, gestion des listes d'élèves
par classe (convention de nommage propre à l'établissement), et provisioning
automatique — pour chaque projet d'élève — d'une base de données dédiée, d'un
utilisateur BDD scoppé, d'un compte SSH/SFTP restreint, et d'un espace de dépôt
web sous `/var/www/html/<nom_eleve>/<projet>/...`.

> Statut : projet en cours de développement (Phase 0 — scaffolding). Voir
> [docs/architecture.md](docs/architecture.md) pour le fonctionnement détaillé.

## Pourquoi

Créer manuellement, à chaque rentrée ou chaque TP, un compte Linux, une base de
données + son utilisateur, et une arborescence web par élève est lent et source
d'erreurs. TP Manager automatise ce provisioning tout en gardant l'enseignant
en contrôle (aucune action n'est faite sans validation explicite dans le
dashboard).

## Architecture en un coup d'œil

Deux composants qui communiquent par une API REST versionnée (voir
[contracts/openapi.yaml](contracts/openapi.yaml)) :

- **`dashboard/`** — application PHP/Symfony utilisée par l'enseignant :
  configuration de l'établissement, gestion des classes/élèves/projets, import
  CSV, suivi du provisioning, distribution des identifiants.
- **`agent/`** — service Python/FastAPI installé sur la VM de TP, seul
  composant à détenir des privilèges système (création de comptes Linux,
  bases de données MySQL/PostgreSQL, arborescence web).

Détails complets : [docs/architecture.md](docs/architecture.md),
[docs/security.md](docs/security.md).

## Démarrer en développement

Un environnement jetable (`tools/fake-vm/`) permet de développer/tester
l'agent sans jamais toucher une vraie VM de TP :

```bash
docker compose -f docker-compose.dev.yml up --build
```

- Dashboard : http://localhost:8080
- Agent (dans le conteneur `fake-vm`) : http://localhost:8000/health

## Documentation

- [Guide utilisateur](docs/guide-utilisateur.md) — installation et usage pour
  les enseignants (à venir).
- [Architecture](docs/architecture.md)
- [Sécurité](docs/security.md) (à venir)
- [Convention de nommage](docs/naming-patterns.md) (à venir)
- [Contribuer](CONTRIBUTING.md)

## Licence

[MIT](LICENSE)
