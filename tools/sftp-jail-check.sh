#!/bin/sh
# Vérifie le confinement chroot SFTP d'un compte élève déjà provisionné.
# Usage: sftp-jail-check.sh <host> <port> <username> <private_key_path> <projet_slug>
#
# Prérequis : authentification par clé publique (le compte doit avoir été
# provisionné avec authMethod=public_key et cette clé). Conçu pour tourner
# contre tools/fake-vm (voir docs, section Phase 3) mais fonctionne contre
# n'importe quel hôte exposant le même schéma de chroot.
set -u

host="$1"
port="$2"
username="$3"
key_path="$4"
projet_slug="$5"

workdir=$(mktemp -d)
trap 'rm -rf "$workdir"' EXIT

pass=0
fail=0

report() {
    label="$1"
    ok="$2"
    if [ "$ok" -eq 0 ]; then
        printf '[OK]   %s\n' "$label"
        pass=$((pass + 1))
    else
        printf '[FAIL] %s\n' "$label"
        fail=$((fail + 1))
    fi
}

# $1 = commandes sftp (une par ligne, newlines réelles) -> écrit stdout/stderr
# dans $workdir, retourne le code de sortie de sftp.
sftp_batch() {
    printf '%s\n' "$1" > "$workdir/batch.txt"
    sftp -i "$key_path" -oStrictHostKeyChecking=no -oBatchMode=yes -P "$port" \
        -b "$workdir/batch.txt" "$username@$host" >"$workdir/stdout.log" 2>"$workdir/stderr.log"
}

echo "some-content" > "$workdir/upload.txt"

# (a) Upload dans son propre dossier projet -> doit réussir.
upload_cmds="cd $projet_slug
put $workdir/upload.txt jail-check.txt"
sftp_batch "$upload_cmds"
report "upload dans /$projet_slug (autorisé)" $?

# (b) cd .. depuis la racine du chroot -> doit rester à la racine (pas d'échappement).
sftp_batch "cd ..
pwd"
pwd_output=$(grep 'Remote working directory' "$workdir/stdout.log" 2>/dev/null || true)
if [ "$pwd_output" = 'Remote working directory: /' ]; then cdup_ok=0; else cdup_ok=1; fi
report "cd .. depuis la racine reste confiné (pwd = /)" "$cdup_ok"

# (c) Lecture de /etc/passwd (chemin absolu) -> doit échouer (fichier inexistant dans le chroot).
sftp_batch "get /etc/passwd $workdir/stolen_passwd"
if [ $? -ne 0 ]; then read_passwd_denied=0; else read_passwd_denied=1; fi
report "lecture de /etc/passwd refusée" "$read_passwd_denied"

echo "---"
echo "$pass test(s) OK, $fail échec(s)."
[ "$fail" -eq 0 ]
