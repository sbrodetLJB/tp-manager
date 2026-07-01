#!/bin/sh
# Installateur idempotent de l'agent tpagent sur une VM Linux.
#
# Usage normal (VM de TP réelle) :
#   Déployer le contenu de agent/ dans /opt/tpagent (git clone/rsync), puis :
#   sudo /opt/tpagent/packaging/install.sh
#   (pour PostgreSQL plutôt que MySQL/MariaDB, le moteur par défaut — "env"
#   garantit que la variable atteint le script quelle que soit la politique
#   de transmission d'environnement de sudo) :
#   sudo env TPAGENT_DB_ENGINE=postgresql /opt/tpagent/packaging/install.sh
#
# Usage dev (conteneur tools/fake-vm, agent/ monté en volume sur /opt/tpagent) :
#   sudo /opt/tpagent/packaging/install.sh --dev
#   (saute l'étape systemd — le conteneur n'a pas d'init — et installe les
#   dépendances Python directement plutôt que dans un venv, pour un démarrage
#   rapide du conteneur jetable)
set -eu

AGENT_HOME=/opt/tpagent
DEV_MODE=0
[ "${1:-}" = "--dev" ] && DEV_MODE=1

if [ "$(id -u)" -ne 0 ]; then
    echo "Ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

log() {
    printf '[install.sh] %s\n' "$*"
}

# 0) Vérification des prérequis documentés (docs/guide-utilisateur.md,
# section "Prérequis") AVANT toute modification du système : mieux vaut un
# échec immédiat et explicite qu'une installation à moitié faite.
check_prerequisites() {
    if ! command -v sshd >/dev/null 2>&1; then
        log "ERREUR : openssh-server introuvable (commande \"sshd\" absente)."
        log "Installez-le puis relancez ce script — voir docs/guide-utilisateur.md, section \"Prérequis\"."
        exit 1
    fi

    python_bin=$(command -v python3 || true)
    if [ -z "$python_bin" ]; then
        log "ERREUR : python3 introuvable."
        log "Installez Python 3.11+ puis relancez ce script — voir docs/guide-utilisateur.md, section \"Prérequis\"."
        exit 1
    fi
    python_major=$("$python_bin" -c 'import sys; print(sys.version_info[0])')
    python_minor=$("$python_bin" -c 'import sys; print(sys.version_info[1])')
    if [ "$python_major" -lt 3 ] || { [ "$python_major" -eq 3 ] && [ "$python_minor" -lt 11 ]; }; then
        log "ERREUR : python3 $python_major.$python_minor détecté, 3.11+ requis."
        log "Mettez Python à jour puis relancez ce script — voir docs/guide-utilisateur.md, section \"Prérequis\"."
        exit 1
    fi

    case "$db_engine" in
        mysql | mariadb)
            if ! command -v mysql >/dev/null 2>&1; then
                log "ERREUR : client \"mysql\" introuvable (TPAGENT_DB_ENGINE=$db_engine)."
                log "Installez et démarrez MySQL/MariaDB puis relancez ce script — voir docs/guide-utilisateur.md, section \"Prérequis\"."
                exit 1
            fi
            if ! mysqladmin ping >/dev/null 2>&1; then
                log "ERREUR : le serveur MySQL/MariaDB ne répond pas (\"mysqladmin ping\" a échoué)."
                log "Démarrez le service puis relancez ce script."
                exit 1
            fi
            ;;
        postgresql | postgres)
            if ! command -v psql >/dev/null 2>&1; then
                log "ERREUR : client \"psql\" introuvable (TPAGENT_DB_ENGINE=$db_engine)."
                log "Installez et démarrez PostgreSQL puis relancez ce script — voir docs/guide-utilisateur.md, section \"Prérequis\"."
                exit 1
            fi
            if ! su -s /bin/sh -c 'pg_isready' postgres >/dev/null 2>&1; then
                log "ERREUR : le serveur PostgreSQL ne répond pas (\"pg_isready\" a échoué)."
                log "Démarrez le service puis relancez ce script."
                exit 1
            fi
            ;;
        *)
            log "ERREUR : TPAGENT_DB_ENGINE=\"$db_engine\" inconnu (attendu : mysql ou postgresql)."
            exit 1
            ;;
    esac

    log "Prérequis vérifiés : openssh-server, python3 ($python_major.$python_minor), moteur \"$db_engine\" joignable."
}

# Le moteur peut venir de la variable d'environnement (premier run, voir
# l'usage ci-dessus) ou avoir été persisté dans tpagent.env par un run
# précédent (docs/runbooks/install-agent-on-vm.md, étape "Choisir le moteur
# de base de données") — sinon mysql par défaut.
db_engine="${TPAGENT_DB_ENGINE:-}"
if [ -z "$db_engine" ] && [ -f "$AGENT_HOME/tpagent.env" ]; then
    db_engine=$(grep '^TPAGENT_DB_ENGINE=' "$AGENT_HOME/tpagent.env" 2>/dev/null | tail -1 | cut -d= -f2)
fi
db_engine="${db_engine:-mysql}"
check_prerequisites

# 1) Groupe et utilisateur système dédiés. tp-students identifie les comptes
# élèves pour le bloc "Match Group" sshd (chroot SFTP, voir étape 3b).
getent group tp-students >/dev/null 2>&1 || groupadd --system tp-students

if ! id tpagent >/dev/null 2>&1; then
    useradd --system --home-dir "$AGENT_HOME" --no-create-home --shell /usr/sbin/nologin tpagent
    log "Utilisateur système tpagent créé."
else
    log "Utilisateur tpagent déjà présent."
fi

mkdir -p "$AGENT_HOME/var"
chown tpagent:tpagent "$AGENT_HOME/var"
chmod 700 "$AGENT_HOME/var"

# 2) Scripts privilégiés : root:root, non modifiables par tpagent (défense en
# profondeur — un RCE dans l'API ne doit pas pouvoir réécrire son propre chemin
# d'escalade).
chown -R root:root "$AGENT_HOME/scripts"
find "$AGENT_HOME/scripts" -name '*.sh' -exec chmod 750 {} \;

# 3) Règle sudoers (validation obligatoire avant activation).
install -m 440 "$AGENT_HOME/packaging/sudoers.d/tpagent" /etc/sudoers.d/tpagent
if ! visudo -c -f /etc/sudoers.d/tpagent >/dev/null; then
    log "Fragment sudoers invalide, annulation."
    rm -f /etc/sudoers.d/tpagent
    exit 1
fi
log "Règle sudoers installée."

# 3b) Config sshd pour le chroot SFTP des comptes élèves (groupe tp-students).
mkdir -p /etc/ssh/sshd_config.d
install -m 644 "$AGENT_HOME/packaging/sshd_config.d/tpagent-sftp.conf" /etc/ssh/sshd_config.d/tpagent-sftp.conf
if command -v sshd >/dev/null 2>&1 && ! sshd -t; then
    log "Configuration sshd invalide après ajout de tpagent-sftp.conf, annulation."
    rm -f /etc/ssh/sshd_config.d/tpagent-sftp.conf
    exit 1
fi
if [ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1; then
    systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null || true
elif [ -f /var/run/sshd.pid ]; then
    kill -HUP "$(cat /var/run/sshd.pid)" 2>/dev/null || true
fi
log "Configuration sshd (chroot SFTP) installée."

# 4) Jeton bearer (généré une seule fois, jamais régénéré silencieusement).
ENV_FILE="$AGENT_HOME/tpagent.env"
if [ ! -f "$ENV_FILE" ]; then
    token=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
    printf 'TPAGENT_BEARER_TOKEN=%s\n' "$token" > "$ENV_FILE"
    chmod 600 "$ENV_FILE"
    chown tpagent:tpagent "$ENV_FILE"
    log "Jeton bearer généré. À coller UNE FOIS dans l'assistant de configuration du dashboard :"
    log "  $token"
else
    log "Jeton bearer déjà configuré dans $ENV_FILE (non régénéré — voir docs/runbooks/rotate-agent-token.md pour le faire tourner)."
fi

# 5) Dépendances Python.
if [ "$DEV_MODE" -eq 1 ]; then
    pip install --quiet --break-system-packages -e "$AGENT_HOME[dev]"
else
    python3 -m venv "$AGENT_HOME/.venv"
    "$AGENT_HOME/.venv/bin/pip" install --quiet -e "$AGENT_HOME"
fi

# 6) Service systemd — uniquement si un vrai systemd pilote l'init (absent des
# conteneurs de dev tools/fake-vm).
if [ "$DEV_MODE" -eq 0 ] && [ -d /run/systemd/system ]; then
    install -m 644 "$AGENT_HOME/packaging/tpagent.service" /etc/systemd/system/tpagent.service
    systemctl daemon-reload
    systemctl enable --now tpagent.service
    log "Service systemd tpagent démarré."
else
    log "Mode dev ou pas de systemd détecté : le service n'est pas démarré ici (l'entrypoint du conteneur s'en charge)."
fi

# 7) Sécurisation du compte administrateur du SGBD. Par défaut, root
# (MySQL/MariaDB) et postgres (PostgreSQL) n'ont AUCUN mot de passe et ne
# sont joignables que localement via unix_socket/peer auth (voir
# docs/security.md) — l'agent continue d'utiliser exclusivement cette
# authentification locale, jamais le mot de passe défini ici, qui n'est ni
# stocké ni journalisé par ce script. Lui donner un mot de passe est une
# défense en profondeur si cette configuration venait à changer (accès
# réseau activé par erreur, plugin d'authentification désactivé...).
prompt_password() {
    printf '%s' "$1"
    stty -echo 2>/dev/null || true
    IFS= read -r reply_password || reply_password=""
    stty echo 2>/dev/null || true
    printf '\n'
}

secure_db_admin_account() {
    prompt_password "Nouveau mot de passe administrateur $db_engine (laisser vide pour ignorer) : "
    password1="$reply_password"

    if [ -z "$password1" ]; then
        log "Aucun mot de passe saisi : compte administrateur du SGBD laissé sans mot de passe (accès local uniquement, voir docs/security.md)."
        return 0
    fi

    prompt_password "Confirmez le mot de passe : "
    password2="$reply_password"

    if [ "$password1" != "$password2" ]; then
        log "Les deux saisies ne correspondent pas : compte administrateur du SGBD non modifié."
        return 0
    fi

    escaped_password=$(printf '%s' "$password1" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g")

    case "$db_engine" in
        mysql | mariadb)
            version_string=$(mysql -N -B -e "SELECT VERSION();" 2>/dev/null || echo "")
            case "$version_string" in
                *MariaDB*)
                    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket OR mysql_native_password USING PASSWORD('$escaped_password');"
                    mysql -e "FLUSH PRIVILEGES;"
                    log "Mot de passe administrateur MariaDB défini (l'accès local de l'agent via unix_socket reste également actif)."
                    ;;
                *)
                    log "AVERTISSEMENT : MySQL (non MariaDB) détecté — combiner unix_socket et mot de passe n'est pas automatisé ici pour ne pas casser l'accès local de l'agent."
                    log "Voir docs/runbooks/secure-db-admin-account.md pour la procédure manuelle."
                    ;;
            esac
            ;;
        postgresql | postgres)
            su -s /bin/sh -c "psql -c \"ALTER ROLE postgres WITH PASSWORD '$escaped_password';\"" postgres >/dev/null
            log "Mot de passe du rôle postgres défini (l'authentification peer locale utilisée par l'agent reste également active)."
            ;;
    esac

    unset password1 password2 escaped_password
}

if [ "$DEV_MODE" -eq 1 ]; then
    log "Mode dev : compte administrateur du SGBD laissé tel quel (conteneur jetable)."
elif [ ! -t 0 ]; then
    log "Entrée non interactive : mot de passe administrateur du SGBD non demandé."
    log "Voir docs/runbooks/secure-db-admin-account.md pour le faire manuellement."
else
    secure_db_admin_account
fi

log "Installation terminée."
