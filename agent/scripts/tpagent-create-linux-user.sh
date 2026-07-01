#!/bin/sh
# Usage: tpagent-create-linux-user.sh create <username> <home_dir> <auth_method> <secret>
#        tpagent-create-linux-user.sh reset-password <username> <auth_method> <secret>
# auth_method: password | public_key
#
# home_dir devient la racine du chroot SFTP (OpenSSH ChrootDirectory) : ce
# dossier doit être root:root, non modifiable par le groupe/other, sans quoi
# sshd refuse silencieusement la connexion ("connection reset"). Le contenu
# réellement déposé par l'élève vit dans des sous-dossiers créés séparément
# par tpagent-create-webroot.sh (owner=élève) — voir docs/security.md.
#
# reset-password change uniquement le secret d'authentification d'un compte
# EXISTANT (chpasswd ou authorized_keys) : ne touche jamais au home ni aux
# fichiers déjà déposés par l'élève — voir docs/security.md.
set -eu
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib/common.sh
. "$SCRIPT_DIR/lib/common.sh"

action="$1"

set_auth_secret() {
    # $1 = username, $2 = home_dir, $3 = auth_method, $4 = secret
    _username="$1"
    _home_dir="$2"
    _auth_method="$3"
    _secret="$4"

    case "$_auth_method" in
        password)
            printf '%s:%s' "$_username" "$_secret" | chpasswd
            ;;
        public_key)
            # .ssh/authorized_keys restent root:root : c'est le provisioning
            # (root) qui les écrit, l'élève n'a jamais besoin d'y écrire.
            ssh_dir="$_home_dir/.ssh"
            mkdir -p "$ssh_dir"
            printf '%s\n' "$_secret" > "$ssh_dir/authorized_keys"
            chown -R root:root "$ssh_dir"
            chmod 0755 "$ssh_dir"
            chmod 0644 "$ssh_dir/authorized_keys"
            ;;
        *)
            log "Méthode d'authentification inconnue : $_auth_method"
            exit 1
            ;;
    esac
}

case "$action" in
    create)
        username="$2"
        home_dir="$3"
        auth_method="$4"
        secret="$5"

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
        set_auth_secret "$username" "$home_dir" "$auth_method" "$secret"

        printf 'uid=%s\n' "$(id -u "$username")"
        exit 0
        ;;
    reset-password)
        username="$2"
        auth_method="$3"
        secret="$4"

        validate_identifier "$username" 32 "username"

        if ! id "$username" >/dev/null 2>&1; then
            log "L'utilisateur $username n'existe pas : impossible de réinitialiser son secret."
            exit 2
        fi

        home_dir=$(getent passwd "$username" | cut -d: -f6)
        set_auth_secret "$username" "$home_dir" "$auth_method" "$secret"

        exit 0
        ;;
    *)
        log "Action inconnue : $action (attendu: create|reset-password)"
        exit 1
        ;;
esac
