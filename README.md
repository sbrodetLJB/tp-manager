# TP Manager

Outil pour les enseignants d'informatique (BTS SIO SLAM) qui gèrent un serveur
de TP sur une VM Linux : configuration du serveur, gestion des listes d'élèves
par classe (convention de nommage propre à l'établissement), et provisioning
automatique — pour chaque projet d'élève — d'une base de données dédiée, d'un
utilisateur BDD scoppé, d'un compte SSH/SFTP restreint, et d'un espace de dépôt
web sous `/var/www/html/<nom_eleve>/<projet>/...`.

> Statut : les 5 phases de développement (scaffolding, CRUD, provisioning MySQL,
> PostgreSQL + SFTP durci, assistant de configuration, déprovisioning + actions
> de masse) sont implémentées et vérifiées de bout en bout contre une VM
> jetable (`tools/fake-vm/`). Voir [docs/architecture.md](docs/architecture.md).

![Page d'un projet provisionné, avec son journal de provisioning](docs/screenshots/05-projet-provisionne.png)

## Pourquoi

Créer manuellement, à chaque rentrée ou chaque TP, un compte Linux, une base de
données + son utilisateur, et une arborescence web par élève est lent et source
d'erreurs. TP Manager automatise ce provisioning tout en gardant l'enseignant
en contrôle (aucune action n'est faite sans validation explicite dans le
dashboard).

## Fonctionnalités

- **Assistant de configuration** guidé (établissement + agent), qui vérifie la
  connexion à l'agent avant d'enregistrer quoi que ce soit.
- **Gestion des classes et élèves** : import CSV avec génération automatique
  du login selon un gabarit de nommage propre à l'établissement (ex:
  `{prenom}.{nom}`), avec gestion des doublons.
- **Provisioning par projet** : compte Linux SSH/SFTP restreint (chroot),
  base de données dédiée (MySQL/MariaDB **ou** PostgreSQL) avec utilisateur
  scoppé à cette seule base, dépôt web dédié.
- **Distribution des identifiants à usage unique** : les mots de passe générés
  ne sont affichés qu'une seule fois, jamais stockés en clair.
- **Déprovisioning et actions de masse** : provisionner/déprovisionner tous
  les projets d'une classe en un clic, avec suivi individuel des réussites/
  échecs et reprise possible après une panne partielle.

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

- Dashboard : http://localhost:8080 (redirige vers l'assistant de configuration si aucun établissement n'existe encore)
- Agent (dans le conteneur `fake-vm`) : http://localhost:8000/health

Moteur de base de données utilisé par le fake-vm : `mysql` par défaut,
surchargeable avec `TPAGENT_DB_ENGINE=postgresql docker compose -f docker-compose.dev.yml up`.

## Installation sur une vraie VM de TP

Voir [docs/guide-utilisateur.md](docs/guide-utilisateur.md) (guide pas-à-pas
pour les enseignants) et [docs/runbooks/install-agent-on-vm.md](docs/runbooks/install-agent-on-vm.md)
(installation détaillée de l'agent).

## Documentation

- [Guide utilisateur](docs/guide-utilisateur.md) — installation et usage pour les enseignants
- [Architecture](docs/architecture.md)
- [Sécurité](docs/security.md)
- [Convention de nommage](docs/naming-patterns.md)
- [Décisions d'architecture](docs/decisions/)
- [Runbooks](docs/runbooks/)
- [Contribuer](CONTRIBUTING.md)

## Licence

[MIT](LICENSE)
