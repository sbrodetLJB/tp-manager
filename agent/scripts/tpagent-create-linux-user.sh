#!/bin/sh
# Usage: tpagent-create-linux-user.sh <username> <home_dir> <auth_method> <secret>
# auth_method: password | public_key
#
# Phase 2 : création simple d'un compte Linux (sans mot de passe shell,
# nologin). L'ownership exacte requise par le chroot SFTP (root:root sur
# home_dir) sera appliquée en Phase 3 — voir docs/security.md.
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

username="$1"
home_dir="$2"
auth_method="$3"
secret="$4"

validate_identifier "$username" 32 "username"

if id "$username" >/dev/null 2>&1; then
    existing_home=$(getent passwd "$username" | cut -d: -f6)
    if [ "$existing_home" != "$home_dir" ]; then
        log "L'utilisateur $username existe déjà avec un home différent ($existing_home != $home_dir)"
        exit 3
    fi
    printf 'uid=%s\n' "$(id -u "$username")"
    exit 2
fi

useradd --create-home --home-dir "$home_dir" --shell /usr/sbin/nologin "$username"

case "$auth_method" in
    password)
        printf '%s:%s' "$username" "$secret" | chpasswd
        ;;
    public_key)
        ssh_dir="$home_dir/.ssh"
        mkdir -p "$ssh_dir"
        printf '%s\n' "$secret" > "$ssh_dir/authorized_keys"
        chmod 700 "$ssh_dir"
        chmod 600 "$ssh_dir/authorized_keys"
        chown -R "$username":"$username" "$ssh_dir"
        ;;
    *)
        log "Méthode d'authentification inconnue : $auth_method"
        exit 1
        ;;
esac

printf 'uid=%s\n' "$(id -u "$username")"
exit 0
