# VM Linux jetable pour développer/tester l'agent tpagent sans jamais toucher
# la vraie VM de TP. Installée progressivement au fil des phases :
# Phase 0 : base Debian + openssh-server (juste pour que le conteneur tourne).
# Phase 2 : ajout de mariadb-server pour le provisioning MySQL.
# Phase 3 : ajout de postgresql pour le provisioning PostgreSQL + config SFTP chroot.
FROM debian:12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    openssh-server \
    sudo \
    python3 \
    python3-pip \
    mariadb-server \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /run/sshd /run/mysqld && chown mysql:mysql /run/mysqld

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 22 8000

ENTRYPOINT ["/entrypoint.sh"]
