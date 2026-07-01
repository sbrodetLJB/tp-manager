#!/bin/sh
# Usage: tpagent-create-webroot.sh <eleve_login> <projet_slug> <owner> <group> <web_root_base>
#
# eleve_home (racine du chroot SFTP, voir tpagent-create-linux-user.sh) reste
# TOUJOURS root:root — seul le sous-dossier projet est possédé par l'élève.
# Ne jamais chown eleve_home avec owner/group ici, sous peine de casser le
# confinement chroot (sshd refuserait alors la connexion).
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

eleve_login="$1"
projet_slug="$2"
owner="$3"
group="$4"
web_root_base="$5"

validate_identifier "$eleve_login" 32 "eleveLogin"
validate_path_segment "$projet_slug" 100 "projetSlug"

eleve_home="$web_root_base/$eleve_login"
projet_path="$eleve_home/$projet_slug"

# Anti path-traversal : le chemin final résolu doit rester sous web_root_base,
# même si eleve_login/projet_slug ont déjà passé la whitelist ci-dessus.
resolved=$(readlink -f -- "$projet_path" 2>/dev/null || printf '%s' "$projet_path")
case "$resolved" in
    "$web_root_base"/*) ;;
    *) log "Chemin hors de $web_root_base : $projet_path"; exit 1 ;;
esac

already_existed=0
[ -d "$projet_path" ] && already_existed=1

mkdir -p "$eleve_home"
chown root:root "$eleve_home"
chmod 0755 "$eleve_home"

mkdir -p "$projet_path"
chown "$owner":"$group" "$projet_path"
chmod 2750 "$projet_path"

printf 'path=%s\n' "$projet_path"

[ "$already_existed" -eq 1 ] && exit 2
exit 0
