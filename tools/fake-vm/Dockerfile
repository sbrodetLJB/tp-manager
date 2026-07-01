# VM Linux jetable pour développer/tester l'agent tpagent sans jamais toucher
# la vraie VM de TP. openssh-server (sshd + chroot SFTP), mariadb-server et
# postgresql sont tous deux installés : le moteur BDD réellement utilisé par
# l'agent est choisi via TPAGENT_DB_ENGINE (voir docker-compose.dev.yml).
FROM debian:12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    openssh-server \
    openssh-client \
    sudo \
    python3 \
    python3-pip \
    mariadb-server \
    postgresql \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /run/sshd /run/mysqld && chown mysql:mysql /run/mysqld

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 22 8000

ENTRYPOINT ["/entrypoint.sh"]
