#!/bin/sh
# Usage: tpagent-create-linux-user.sh <username> <home_dir> <auth_method> <secret>
# auth_method: password | public_key
#
# home_dir devient la racine du chroot SFTP (OpenSSH ChrootDirectory) : ce
# dossier doit être root:root, non modifiable par le groupe/other, sans quoi
# sshd refuse silencieusement la connexion ("connection reset"). Le contenu
# réellement déposé par l'élève vit dans des sous-dossiers créés séparément
# par tpagent-create-webroot.sh (owner=élève) — voir docs/security.md.
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

mkdir -p "$home_dir"
chown root:root "$home_dir"
chmod 0755 "$home_dir"

useradd --no-create-home --home-dir "$home_dir" --shell /usr/sbin/nologin --groups tp-students "$username"

case "$auth_method" in
    password)
        printf '%s:%s' "$username" "$secret" | chpasswd
        ;;
    public_key)
        # .ssh/authorized_keys restent root:root : c'est le provisioning (root)
        # qui les écrit, l'élève n'a jamais besoin d'y écrire lui-même.
        ssh_dir="$home_dir/.ssh"
        mkdir -p "$ssh_dir"
        printf '%s\n' "$secret" > "$ssh_dir/authorized_keys"
        chown -R root:root "$ssh_dir"
        chmod 0755 "$ssh_dir"
        chmod 0644 "$ssh_dir/authorized_keys"
        ;;
    *)
        log "Méthode d'authentification inconnue : $auth_method"
        exit 1
        ;;
esac

printf 'uid=%s\n' "$(id -u "$username")"
exit 0
