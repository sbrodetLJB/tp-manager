#!/bin/sh
# Usage: tpagent-mysql-provision.sh create         <db_name> <db_user> <db_password> [charset]
#        tpagent-mysql-provision.sh drop           <db_name> <db_user>
#        tpagent-mysql-provision.sh reset-password <db_name> <db_user> <db_password>
#
# Utilise l'authentification unix_socket de root (aucun mot de passe root
# MySQL stocké nulle part) — voir docs/security.md.
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

action="$1"
db_name="$2"
db_user="$3"

validate_identifier "$db_name" 63 "dbName"
validate_identifier "$db_user" 63 "dbUser"

case "$action" in
    create)
        db_password="$4"
        charset="${5:-utf8mb4}"

        db_exists=$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$db_name'")
        user_exists=$(mysql -N -B -e "SELECT COUNT(*) FROM mysql.user WHERE User = '$db_user' AND Host = 'localhost'")

        if [ "$db_exists" -eq 1 ] && [ "$user_exists" -eq 1 ]; then
            log "La base $db_name et l'utilisateur $db_user existent déjà."
            exit 2
        fi
        if [ "$db_exists" -eq 1 ] || [ "$user_exists" -eq 1 ]; then
            log "État incohérent pour $db_name/$db_user (base ou utilisateur existe partiellement)."
            exit 3
        fi

        # Le mot de passe n'est pas un identifiant whitelisté : on échappe
        # backslash et quote simple avant de l'insérer dans le littéral SQL.
        escaped_password=$(printf '%s' "$db_password" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g")

        mysql -e "CREATE DATABASE \`$db_name\` CHARACTER SET $charset;"
        mysql -e "CREATE USER '$db_user'@'localhost' IDENTIFIED BY '$escaped_password';"
        # WITH GRANT OPTION : l'élève peut déléguer (GRANT/REVOKE) des
        # sous-ensembles de ses propres droits à des comptes déjà existants
        # sur SA base (ex: un compte applicatif en lecture seule), sans que
        # cela déborde sur les autres bases — la portée reste "database-only"
        # car GRANT OPTION ne peut re-déléguer que ce que ce compte possède
        # déjà, c'est-à-dire uniquement des droits sur $db_name. L'élève ne
        # peut toujours pas créer de nouveaux comptes lui-même (CREATE USER
        # est un privilège global, volontairement non accordé) — voir
        # docs/security.md.
        mysql -e "GRANT ALL PRIVILEGES ON \`$db_name\`.* TO '$db_user'@'localhost' WITH GRANT OPTION;"
        mysql -e "FLUSH PRIVILEGES;"
        exit 0
        ;;
    drop)
        db_exists=$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$db_name'")
        user_exists=$(mysql -N -B -e "SELECT COUNT(*) FROM mysql.user WHERE User = '$db_user' AND Host = 'localhost'")

        if [ "$db_exists" -eq 0 ] && [ "$user_exists" -eq 0 ]; then
            log "La base $db_name et l'utilisateur $db_user n'existent pas (déjà supprimés)."
            exit 2
        fi

        mysql -e "DROP DATABASE IF EXISTS \`$db_name\`;"
        mysql -e "DROP USER IF EXISTS '$db_user'@'localhost';"
        mysql -e "FLUSH PRIVILEGES;"
        exit 0
        ;;
    reset-password)
        db_password="$4"

        user_exists=$(mysql -N -B -e "SELECT COUNT(*) FROM mysql.user WHERE User = '$db_user' AND Host = 'localhost'")
        if [ "$user_exists" -eq 0 ]; then
            log "L'utilisateur $db_user n'existe pas : impossible de réinitialiser son mot de passe."
            exit 2
        fi

        escaped_password=$(printf '%s' "$db_password" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g")

        # ALTER USER change uniquement le secret d'authentification : ne
        # touche ni à la base ni aux GRANT déjà accordés — voir docs/security.md.
        mysql -e "ALTER USER '$db_user'@'localhost' IDENTIFIED BY '$escaped_password';"
        mysql -e "FLUSH PRIVILEGES;"
        exit 0
        ;;
    *)
        log "Action inconnue : $action (attendu: create|drop|reset-password)"
        exit 1
        ;;
esac
