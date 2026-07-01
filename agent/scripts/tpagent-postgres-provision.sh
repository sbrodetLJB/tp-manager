#!/bin/sh
# Usage: tpagent-postgres-provision.sh create         <db_name> <db_user> <db_password>
#        tpagent-postgres-provision.sh drop           <db_name> <db_user>
#        tpagent-postgres-provision.sh reset-password <db_name> <db_user> <db_password>
#
# Utilise l'authentification "peer" du rôle système postgres (aucun mot de
# passe superuser PostgreSQL stocké nulle part) — voir docs/security.md.
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

action="$1"
db_name="$2"
db_user="$3"

validate_identifier "$db_name" 63 "dbName"
validate_identifier "$db_user" 63 "dbUser"

run_psql() {
    printf '%s\n' "$1" | su -s /bin/sh -c 'psql -tA' postgres
}

case "$action" in
    create)
        db_password="$4"

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

        escaped_password=$(printf '%s' "$db_password" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g")

        run_psql "CREATE ROLE \"$db_user\" LOGIN PASSWORD '$escaped_password';"
        run_psql "CREATE DATABASE \"$db_name\" OWNER \"$db_user\";"
        run_psql "REVOKE ALL ON DATABASE \"$db_name\" FROM PUBLIC;"
        exit 0
        ;;
    drop)
        db_exists=$(run_psql "SELECT 1 FROM pg_database WHERE datname='$db_name';" | tr -d '[:space:]')
        user_exists=$(run_psql "SELECT 1 FROM pg_roles WHERE rolname='$db_user';" | tr -d '[:space:]')

        if [ "$db_exists" != "1" ] && [ "$user_exists" != "1" ]; then
            log "La base $db_name et le rôle $db_user n'existent pas (déjà supprimés)."
            exit 2
        fi

        if [ "$db_exists" = "1" ]; then
            run_psql "DROP DATABASE \"$db_name\";"
        fi
        if [ "$user_exists" = "1" ]; then
            run_psql "DROP ROLE \"$db_user\";"
        fi
        exit 0
        ;;
    reset-password)
        db_password="$4"

        user_exists=$(run_psql "SELECT 1 FROM pg_roles WHERE rolname='$db_user';" | tr -d '[:space:]')
        if [ "$user_exists" != "1" ]; then
            log "Le rôle $db_user n'existe pas : impossible de réinitialiser son mot de passe."
            exit 2
        fi

        escaped_password=$(printf '%s' "$db_password" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g")

        # ALTER ROLE change uniquement le secret d'authentification : ne
        # touche ni à la base ni à son ownership — voir docs/security.md.
        run_psql "ALTER ROLE \"$db_user\" LOGIN PASSWORD '$escaped_password';"
        exit 0
        ;;
    *)
        log "Action inconnue : $action (attendu: create|drop|reset-password)"
        exit 1
        ;;
esac
