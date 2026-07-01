# Architecture

## Vue d'ensemble

TP Manager est composé de deux applications indépendantes qui ne communiquent
qu'à travers une API REST versionnée :

```
┌─────────────────────────┐        HTTPS + Bearer token        ┌──────────────────────────┐
│        dashboard/        │ ─────────────────────────────────▶ │          agent/           │
│   PHP / Symfony           │        contracts/openapi.yaml       │   Python / FastAPI        │
│   (poste enseignant ou    │ ◀───────────────────────────────── │   (installé sur la VM     │
│    serveur d'admin)       │                                     │    de TP, privilégié)     │
└─────────────────────────┘                                      └──────────────────────────┘
                                                                              │
                                                                  useradd / GRANT SQL / mkdir
                                                                  via scripts sudo à chemin fixe
                                                                              ▼
                                                                  ┌──────────────────────────┐
                                                                  │   VM Linux de TP          │
                                                                  │   MySQL/MariaDB           │
                                                                  │   ou PostgreSQL           │
                                                                  │   /var/www/html/...       │
                                                                  │   OpenSSH (SFTP chrooté)  │
                                                                  └──────────────────────────┘
```

Le dashboard ne se connecte jamais en SSH à la VM et n'a jamais de privilège
système : il pilote l'agent via son API. C'est l'agent qui traduit chaque
requête en actions système (comptes Linux, bases de données, dossiers), via
des scripts shell à chemin fixe autorisés par une règle `sudoers` restreinte
(voir [docs/security.md](security.md)).

## Composants

### `dashboard/` (PHP / Symfony)

Utilisé uniquement par l'enseignant (pas de compte élève en V1). Responsable
de :
- la configuration de l'établissement (choix du moteur BDD, URL/token de
  l'agent, chemin web de base) ;
- la gestion des classes, élèves (import CSV + convention de nommage
  configurable) et projets ;
- l'orchestration du provisioning (appelle l'agent dans l'ordre : compte
  Linux → base de données → dossier web) et le suivi de son état ;
- la distribution des identifiants générés (affichage à usage unique).

Persistance : SQLite (`dashboard/var/data_*.db`) — c'est le stockage propre à
l'application (établissements, classes, élèves, projets), à ne pas confondre
avec le moteur BDD choisi pour les projets des élèves sur la VM de TP.

### `agent/` (Python / FastAPI)

Installé comme service systemd sur la VM de TP. Seul composant à détenir des
privilèges système, via un utilisateur dédié non-root (`tpagent`) et des
scripts sudo à chemin fixe. Expose une API REST (`/health`, puis à partir de
la Phase 2 : `/v1/config`, `/v1/linux-accounts`, `/v1/databases`,
`/v1/webroots`) décrite dans [contracts/openapi.yaml](../contracts/openapi.yaml).

Le provisioning de base de données est écrit derrière une interface commune
(`DbProvisioner`) implémentée à la fois pour MySQL/MariaDB et PostgreSQL, le
moteur actif étant déterminé par la configuration de l'établissement.

### `contracts/`

Le fichier `openapi.yaml` est la source de vérité de l'API entre les deux
applications (écrites dans deux langages différents, sans types partagés).
Toute évolution du contrat s'y fait en premier, puis est répercutée côté
client (`dashboard/src/Service/Agent`) et côté serveur (`agent/src/tpagent/api`).

### `tools/fake-vm/`

VM Linux jetable (conteneur Docker) utilisée pour développer et tester
l'agent — comptes Linux, bases de données, chroot SFTP — sans jamais toucher
une vraie VM de TP pendant le développement.

## Pourquoi ce découpage plutôt qu'une seule application sur la VM ?

Séparer le dashboard de l'agent permet à l'enseignant d'administrer plusieurs
TP depuis un même poste, garde la VM de TP la plus légère possible (pas de
serveur web applicatif complet dessus), et confine strictement les privilèges
système à un seul petit composant auditable (l'agent).
