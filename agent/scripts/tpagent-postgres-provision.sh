#!/bin/sh
# Usage: tpagent-postgres-provision.sh <db_name> <db_user> <db_password>
#
# Utilise l'authentification "peer" du rôle système postgres (aucun mot de
# passe superuser PostgreSQL stocké nulle part) — voir docs/security.md.
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

db_name="$1"
db_user="$2"
db_password="$3"

validate_identifier "$db_name" 63 "dbName"
validate_identifier "$db_user" 63 "dbUser"

run_psql() {
    printf '%s\n' "$1" | su -s /bin/sh -c 'psql -tA' postgres
}

db_exists=$(run_psql "SELECT 1 FROM pg_database WHERE datname='$db_name';" | tr -d '[:space:]')
user_exists=$(run_psql "SELECT 1 FROM pg_roles WHERE rolname='$db_user';" | tr -d '[:space:]')

if [ "$db_exists" = "1" ] && [ "$user_exists" = "1" ]; then
    log "La base $db_name et le rôle $db_user existent déjà."
    exit 2
fi

if [ "$db_exists" = "1" ] || [ "$user_exists" = "1" ]; then
    log "État incohérent pour $db_name/$db_user (base ou rôle existe partiellement)."
    exit 3
fi

# Le mot de passe n'est pas un identifiant whitelisté : échappement défensif
# du même type que côté MySQL (voir tpagent-mysql-provision.sh).
escaped_password=$(printf '%s' "$db_password" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g")

run_psql "CREATE ROLE \"$db_user\" LOGIN PASSWORD '$escaped_password';"
run_psql "CREATE DATABASE \"$db_name\" OWNER \"$db_user\";"
# Postgres 15+ ne donne déjà plus CREATE sur le schéma public à PUBLIC par
# défaut ; on retire en plus le CONNECT pour que seuls le propriétaire et le
# superuser puissent se connecter à cette base (grantsScope: database-only).
run_psql "REVOKE ALL ON DATABASE \"$db_name\" FROM PUBLIC;"

exit 0
