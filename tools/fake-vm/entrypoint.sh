#!/bin/sh
set -eu

/usr/sbin/sshd

# --- MariaDB (Phase 2 : moteur MySQL/MariaDB pour le provisioning des BDD) ---
if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/tmp/mariadb-install-db.log 2>&1
fi
mariadbd --user=mysql &

# Attend que le socket MariaDB soit prêt avant de démarrer l'agent (dont les
# scripts de provisioning s'y connectent au premier appel).
for _ in $(seq 1 30); do
    mysqladmin ping >/dev/null 2>&1 && break
    sleep 1
done

# --- Agent tpagent (utilisateur système + sudoers + dépendances Python) ---
sh /opt/tpagent/packaging/install.sh --dev

export TPAGENT_BEARER_TOKEN=$(grep -o 'TPAGENT_BEARER_TOKEN=.*' /opt/tpagent/tpagent.env | cut -d= -f2)
export TPAGENT_DB_ENGINE=mysql
export TPAGENT_SCRIPTS_DIR=/opt/tpagent/scripts
export TPAGENT_JOB_LEDGER_PATH=/opt/tpagent/var/job_ledger.sqlite
export TPAGENT_WEB_ROOT_BASE=/var/www/html

mkdir -p /var/www/html

# Note : ici l'API tourne comme root (process du conteneur), pas comme
# l'utilisateur système "tpagent" — simplification propre au conteneur de dev
# jetable. Sur une vraie VM, install.sh + tpagent.service (User=tpagent) sont
# ce qui applique réellement le principe de moindre privilège (voir docs/security.md).
exec python3 -m uvicorn tpagent.main:app --app-dir /opt/tpagent/src --host 0.0.0.0 --port 8000
