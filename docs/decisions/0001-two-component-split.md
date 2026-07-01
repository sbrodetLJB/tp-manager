# 0001 — Séparer dashboard et agent en deux composants

## Contexte

Le provisioning (comptes Linux, bases de données, dossiers web) nécessite des
privilèges système sur la VM de TP. L'interface de gestion (classes, élèves,
projets) n'en a pas besoin.

## Décision

Deux applications séparées communiquant par une API REST versionnée
(`contracts/openapi.yaml`) :
- `dashboard/` (PHP/Symfony) — aucun privilège, peut tourner n'importe où ;
- `agent/` (Python/FastAPI) — installé sur la VM de TP, seul détenteur des
  privilèges (via sudo restreint à 6 scripts fixes).

## Alternatives envisagées

- **Une seule application hébergée sur la VM de TP** : plus simple à
  déployer (un seul serveur), mais concentre toute la surface d'attaque web
  (dashboard + dépendances PHP/Symfony) sur la machine qui détient déjà les
  privilèges système, et empêche de gérer plusieurs TP depuis un même poste.
- **Dashboard qui se connecte en SSH direct à la VM** : évite d'écrire un
  agent, mais oblige à stocker/faire circuler des identifiants SSH complets
  côté dashboard, et rend le contrat d'API implicite (des commandes shell
  plutôt qu'un schéma explicite versionné).

## Conséquences

- Un contrat d'API à maintenir en synchronisation entre deux langages —
  atténué par `contracts/openapi.yaml` comme source de vérité unique et des
  tests de contrat des deux côtés.
- Le dashboard ne peut rien faire sans qu'un agent soit accessible et
  vérifié (assistant de configuration, Phase 4).
