#!/bin/sh
# Usage: tpagent-delete-linux-user.sh <username> <purge_home:true|false>
#
# N'utilise JAMAIS "userdel -r" : le home (racine du chroot SFTP) est
# volontairement root:root, pas owned par l'utilisateur (voir
# tpagent-create-linux-user.sh), donc userdel refuse de le supprimer et sort
# en erreur même quand la suppression du compte a réussi. On supprime le
# compte puis, si demandé, le home nous-mêmes (rm -rf, sans cette contrainte).
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

username="$1"
purge_home="$2"

validate_identifier "$username" 32 "username"

if ! id "$username" >/dev/null 2>&1; then
    log "L'utilisateur $username n'existe pas (déjà supprimé)."
    exit 2
fi

home_dir=$(getent passwd "$username" | cut -d: -f6)

userdel "$username"

if [ "$purge_home" = "true" ] && [ -n "$home_dir" ] && [ -d "$home_dir" ]; then
    rm -rf "$home_dir"
fi

exit 0
