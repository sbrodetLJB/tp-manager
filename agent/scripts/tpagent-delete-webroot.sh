#!/bin/sh
# Usage: tpagent-delete-webroot.sh <eleve_login> <projet_slug> <web_root_base>
#
# Ne supprime QUE le sous-dossier projet, jamais la racine élève (chroot SFTP,
# potentiellement partagée par d'autres projets) — garde-fou explicite en plus
# du confinement anti path-traversal déjà présent dans create-webroot.sh.
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

eleve_login="$1"
projet_slug="$2"
web_root_base="$3"

validate_identifier "$eleve_login" 32 "eleveLogin"
validate_path_segment "$projet_slug" 100 "projetSlug"

eleve_home="$web_root_base/$eleve_login"
projet_path="$eleve_home/$projet_slug"

resolved_projet=$(readlink -f -- "$projet_path" 2>/dev/null || printf '%s' "$projet_path")
case "$resolved_projet" in
    "$web_root_base"/*) ;;
    *) log "Chemin hors de $web_root_base : $projet_path"; exit 1 ;;
esac

resolved_home=$(readlink -f -- "$eleve_home" 2>/dev/null || printf '%s' "$eleve_home")
if [ "$resolved_projet" = "$resolved_home" ]; then
    log "Refus de supprimer la racine élève elle-même : $eleve_home"
    exit 1
fi

if [ ! -d "$projet_path" ]; then
    log "Le dossier $projet_path n'existe pas (déjà supprimé)."
    exit 2
fi

rm -rf "$projet_path"
exit 0
