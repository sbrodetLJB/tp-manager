# Contribuer

Projet développé en solo, greenfield, par phases (voir le plan d'implémentation
pour le détail). Ces conventions visent à garder le dépôt exploitable par
d'autres enseignants qui voudraient l'adapter à leur établissement.

## Environnement de développement

```bash
docker compose -f docker-compose.dev.yml up --build
```

Ne jamais développer/tester l'agent contre une vraie VM de TP : utiliser
`tools/fake-vm/` (conteneur Debian jetable avec sshd + agent monté en volume).

### Dashboard (PHP/Symfony)

```bash
cd dashboard
composer install
php bin/phpunit
```

### Agent (Python/FastAPI)

```bash
cd agent
python -m venv .venv
./.venv/Scripts/pip install -e ".[dev]"   # ou .venv/bin/pip sur Linux/macOS
pytest
```

## Convention de contrat API

Toute modification de l'API agent commence par `contracts/openapi.yaml`, puis
est répercutée côté client (`dashboard/src/Service/Agent`) et côté serveur
(`agent/src/tpagent/api`). Les tests de contrat (`*/tests/*/contract`)
valident que les deux côtés restent alignés.

## Commits

Un commit = un changement cohérent et testé. Préfixer si utile par le
composant concerné (`agent:`, `dashboard:`, `docs:`).
