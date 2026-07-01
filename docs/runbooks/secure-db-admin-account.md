# Sécuriser le compte administrateur du SGBD

Par défaut, le compte administrateur du SGBD utilisé par l'agent (`root`
pour MySQL/MariaDB, `postgres` pour PostgreSQL) n'a aucun mot de passe : il
n'est joignable que localement, via l'authentification `unix_socket`
(MySQL/MariaDB) ou `peer` (PostgreSQL) — voir [security.md](../security.md).
`install.sh` propose, en fin d'installation, de lui ajouter un mot de passe
en plus de cette authentification locale (défense en profondeur : si la
configuration réseau du SGBD venait à changer par erreur, un mot de passe
réel protège mieux qu'un mot de passe vide).

Ce mot de passe n'est **jamais** utilisé par l'agent ensuite (il continue
d'utiliser exclusivement l'authentification locale) et n'est ni stocké ni
journalisé par `install.sh` — à noter de côté si vous voulez pouvoir vous
reconnecter avec, sinon l'accès local (root/sudo sur la VM) reste toujours
suffisant pour administrer le SGBD.

## Cas 1 — Vous avez répondu "vide" à l'invite, ou l'installation était non interactive

`install.sh` n'écrase jamais rien silencieusement : relancez-le simplement
(`sudo /opt/tpagent/packaging/install.sh`) sur un terminal interactif, toutes
les autres étapes étant idempotentes, seule l'invite de mot de passe sera
à nouveau proposée.

## Cas 2 — Définir le mot de passe manuellement (MariaDB)

```bash
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket OR mysql_native_password USING PASSWORD('VOTRE_MOT_DE_PASSE');"
sudo mysql -e "FLUSH PRIVILEGES;"
```

La clause `IDENTIFIED VIA ... OR ...` (spécifique à MariaDB) conserve
l'authentification `unix_socket` utilisée par l'agent tout en ajoutant un
mot de passe comme méthode alternative — vérifiez que l'accès local
fonctionne toujours avant de fermer votre session :

```bash
mysql -e "SELECT CURRENT_USER();"   # doit répondre root@localhost sans -p
```

## Cas 3 — MySQL (Oracle), pas MariaDB

`install.sh` détecte ce cas et n'automatise rien : la combinaison
"authentification locale sans mot de passe + mot de passe de secours" que
propose MariaDB via `IDENTIFIED VIA ... OR ...` n'a pas d'équivalent direct
sur MySQL (Oracle). Y définir un mot de passe classique
(`ALTER USER ... IDENTIFIED BY ...`) remplace entièrement le plugin
`auth_socket` et **casserait l'accès local de l'agent**, qui suppose une
authentification sans mot de passe pour l'utilisateur système root.

Si vous devez absolument sécuriser ce compte sur une installation MySQL
(Oracle), la solution consiste à créer un compte administrateur *distinct*
avec mot de passe pour votre propre usage interactif, et à laisser
`root`@`localhost` en `auth_socket` pour l'agent :

```bash
sudo mysql -e "CREATE USER 'admin_sgbd'@'localhost' IDENTIFIED BY 'VOTRE_MOT_DE_PASSE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'admin_sgbd'@'localhost' WITH GRANT OPTION;"
sudo mysql -e "FLUSH PRIVILEGES;"
```

## Cas 4 — PostgreSQL

```bash
sudo -u postgres psql -c "ALTER ROLE postgres WITH PASSWORD 'VOTRE_MOT_DE_PASSE';"
```

Ajouter un mot de passe au rôle `postgres` n'affecte jamais l'authentification
`peer` locale (c'est `pg_hba.conf`, pas le mot de passe du rôle, qui décide de
la méthode utilisée par connexion) — l'agent continue de fonctionner sans
changement.
