# 0003 — SQLite pour le stockage propre du dashboard (V1)

## Contexte

Le dashboard a besoin de persister ses propres données (établissement,
classes, élèves, projets, journal de provisioning) — à ne pas confondre avec
le moteur de base de données choisi pour les projets des élèves sur la VM de
TP (voir [0002](0002-db-engine-abstraction.md)).

## Décision

SQLite (`dashboard/var/data_*.db`) en V1, via Doctrine DBAL/ORM — ce qui rend
un changement futur vers MySQL/PostgreSQL une question de `DATABASE_URL` et
d'une migration Doctrine, pas une réécriture.

## Alternatives envisagées

- **MySQL/PostgreSQL dès la V1** : plus proche d'un déploiement multi-
  utilisateurs, mais ajoute une dépendance d'infrastructure (un serveur de
  base de données à installer et sauvegarder) pour un outil pensé pour un
  usage mono-enseignant/mono-établissement local.

## Conséquences

- Sauvegarde simplifiée : un seul fichier à copier (voir
  [runbooks/restore-from-backup.md](../runbooks/restore-from-backup.md)).
- Pas adapté à un accès concurrent important (plusieurs enseignants
  simultanés sur le même établissement) — hors périmètre V1 (single
  établissement, single VM).
