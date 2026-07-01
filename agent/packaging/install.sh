#!/bin/sh
# Installateur idempotent de l'agent tpagent sur une VM Linux.
#
# Usage normal (VM de TP réelle) :
#   Déployer le contenu de agent/ dans /opt/tpagent (git clone/rsync), puis :
#   sudo /opt/tpagent/packaging/install.sh
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

log "Installation terminée."
