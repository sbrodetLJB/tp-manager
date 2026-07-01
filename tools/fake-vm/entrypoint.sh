#!/bin/sh
set -eu

/usr/sbin/sshd

# --- MariaDB (moteur MySQL/MariaDB pour le provisioning des BDD) ---
if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/tmp/mariadb-install-db.log 2>&1
fi
mariadbd --user=mysql &

for _ in $(seq 1 30); do
    mysqladmin ping >/dev/null 2>&1 && break
    sleep 1
done

# --- PostgreSQL (Phase 3 : moteur alternatif pour le provisioning des BDD) ---
PGVER=$(ls /etc/postgresql 2>/dev/null | head -1)
if [ -n "$PGVER" ]; then
    pg_ctlcluster "$PGVER" main start
    for _ in $(seq 1 30); do
        su -s /bin/sh -c 'pg_isready' postgres >/dev/null 2>&1 && break
        sleep 1
    done
fi

# --- Agent tpagent (utilisateur système + sudoers + sshd chroot + dépendances Python) ---
sh /opt/tpagent/packaging/install.sh --dev

export TPAGENT_BEARER_TOKEN=$(grep -o 'TPAGENT_BEARER_TOKEN=.*' /opt/tpagent/tpagent.env | cut -d= -f2)
export TPAGENT_DB_ENGINE="${TPAGENT_DB_ENGINE:-mysql}"
export TPAGENT_SCRIPTS_DIR=/opt/tpagent/scripts
export TPAGENT_JOB_LEDGER_PATH=/opt/tpagent/var/job_ledger.sqlite
export TPAGENT_WEB_ROOT_BASE=/var/www/html

mkdir -p /var/www/html

echo "[entrypoint] TPAGENT_DB_ENGINE=$TPAGENT_DB_ENGINE"

# Note : ici l'API tourne comme root (process du conteneur), pas comme
# l'utilisateur système "tpagent" — simplification propre au conteneur de dev
# jetable. Sur une vraie VM, install.sh + tpagent.service (User=tpagent) sont
# ce qui applique réellement le principe de moindre privilège (voir docs/security.md).
exec python3 -m uvicorn tpagent.main:app --app-dir /opt/tpagent/src --host 0.0.0.0 --port 8000
