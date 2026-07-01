# 0002 — Abstraire le moteur de base de données (MySQL/PostgreSQL)

## Contexte

Les établissements n'utilisent pas tous le même moteur pour leurs TP web
(LAMP historique vs PostgreSQL). Le choix devait être pris en compte dès la
conception plutôt qu'ajouté après coup avec un moteur "en dur".

## Décision

Une interface commune `DbProvisioner` (`agent/src/tpagent/services/db_provisioning/base.py`)
avec deux implémentations (`MysqlProvisioner`, `PostgresProvisioner`),
sélectionnées par une factory selon la configuration de l'agent
(`TPAGENT_DB_ENGINE`). Le choix est fait une fois, au niveau de
l'établissement, et vérifié par le dashboard contre la configuration réelle
de l'agent (`GET /v1/config`) avant toute opération — jamais supposé.

## Alternatives envisagées

- **MySQL uniquement en V1, PostgreSQL "plus tard"** : plus rapide à livrer,
  mais risque fort de coupler l'implémentation au vocabulaire MySQL
  (`GRANT`, auth `unix_socket`) au point de rendre l'ajout de PostgreSQL
  coûteux. L'interface a été conçue dès la Phase 2 même si PostgreSQL n'a été
  implémenté qu'en Phase 3, pour éviter ce piège.
- **Les deux moteurs actifs simultanément par projet** : plus flexible, mais
  complexifie inutilement la configuration pour le cas d'usage réel (un
  établissement a un seul serveur de TP avec un seul moteur installé).

## Conséquences

- Chaque moteur a son propre script shell de provisioning
  (`tpagent-mysql-provision.sh`, `tpagent-postgres-provision.sh`), avec ses
  propres modalités d'authentification admin (`unix_socket` pour MySQL,
  `peer` pour PostgreSQL) — aucun mot de passe superuser stocké dans les deux
  cas.
- Changer le moteur d'un établissement après provisioning existant n'est pas
  géré (les projets déjà créés gardent une copie figée de leur moteur
  d'origine, `Projet::dbEngine`).
