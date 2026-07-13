# Installer un serveur complet (agent + dashboard) sur une VM Debian neuve

Ce runbook part d'une VM Debian fraîchement installée dont **seul le réseau a
été configuré** (adresse IP joignable, rien d'autre — pas de paquets
supplémentaires, pas de compte applicatif). Il détaille, dans l'ordre, tout ce
qu'il faut faire pour obtenir un serveur tp-manager complet et vérifié :
agent + dashboard, sur la même machine.

Il consolide et corrige, à partir du dépôt actuel, les étapes réellement
suivies lors d'une installation de production sur une VM Debian/Ubuntu
comparable — y compris les prérequis qui manquaient et les deux bugs
rencontrés (déjà corrigés dans ce dépôt, voir [../security.md](../security.md)
et le commentaire en tête de
[../../agent/packaging/tpagent.service](../../agent/packaging/tpagent.service)).
Sur un dépôt à jour, ces deux bugs ne devraient plus se reproduire ; ce
runbook les mentionne quand même brièvement, au cas où vous installeriez
depuis une version plus ancienne.

Pour l'installation de l'agent seul, avec plus de détail sur chaque étape et
une section dépannage dédiée, voir aussi
[install-agent-on-vm.md](install-agent-on-vm.md). Ce runbook-ci va plus vite
sur l'agent mais couvre en plus la préparation du système et le dashboard, ce
que l'autre ne fait pas.

## Prérequis

- Une VM Debian (11 "bullseye" ou plus récent — testé sur l'équivalent
  Ubuntu 24.04) avec un accès root ou sudo, et une IP joignable depuis le
  poste où vous administrerez le dashboard.
- Accès Internet sortant depuis la VM (pour `apt`, `git clone` et
  `composer install`).
- Décider **avant de commencer** :
  - le moteur de base de données des projets élèves : `mariadb-server`
    (recommandé, utilisé ci-dessous) ou PostgreSQL — voir
    [../decisions/0002-db-engine-abstraction.md](../decisions/0002-db-engine-abstraction.md) ;
  - le dossier racine des projets web des élèves (`web_root_base`),
    généralement `/var/www/html` — voir l'étape 6 ci-dessous **avant** le
    premier provisioning d'élève, pas après.

Si vous êtes connecté en `root` directement (fréquent sur une VM Debian
minimale, qui n'installe pas `sudo` par défaut), préfixez les commandes
`sudo` ci-dessous en les exécutant simplement sans `sudo` — ou installez-le
d'abord : `apt-get install -y sudo` puis ajoutez votre utilisateur au groupe
`sudo`.

## 1. Préparer le système

Une installation minimale de Debian n'a ni `git`, ni `openssh-server`, ni
`python3-venv` par défaut — les trois sont nécessaires et ne sont pas
vérifiés avant coup par `install.sh` (sauf `python3-venv`, vérifié depuis peu,
voir plus bas) :

```bash
sudo apt-get update
sudo apt-get install -y git curl ca-certificates openssh-server \
    python3 python3-venv
```

Vérifiez que `sshd` est bien actif (nécessaire pour l'administration à
distance ET pour la livraison SFTP des projets élèves, qui passe par le même
service) :

```bash
sudo systemctl enable --now ssh
```

## 2. Installer le moteur de base de données

```bash
sudo apt-get install -y mariadb-server
sudo systemctl enable --now mariadb
```

Ne configurez pas encore de mot de passe root MariaDB : `install.sh` (étape
7 ci-dessous) propose de le faire lui-même de façon interactive. Si vous
préférez le faire séparément (ou après coup), voir
[secure-db-admin-account.md](secure-db-admin-account.md).

## 3. Déployer et installer l'agent

```bash
git clone https://github.com/sbrodetLJB/tp-manager.git /tmp/tp-manager
sudo mkdir -p /opt/tpagent
sudo cp -r /tmp/tp-manager/agent/* /opt/tpagent/
```

`/opt/tpagent` est le chemin attendu par `install.sh` et par le fragment
sudoers (chemins fixes, voir [../security.md](../security.md)) — ne pas le
changer sans adapter `packaging/sudoers.d/tpagent` en conséquence.

```bash
sudo env TPAGENT_DB_ENGINE=mysql /opt/tpagent/packaging/install.sh
```

Lancez cette commande **dans un vrai terminal interactif** (pas via un script
d'automatisation ni un pipe) : la dernière étape propose de définir tout de
suite un mot de passe administrateur MariaDB, et cette invite est
automatiquement sautée si l'entrée n'est pas interactive.

L'installateur vérifie ses prérequis avant toute modification du système
(dont, désormais, la présence du module `venv` de Python — message d'erreur
explicite avec le nom du paquet à installer si absent), puis : crée
l'utilisateur système `tpagent`, installe le fragment sudoers et la
configuration sshd du chroot SFTP (chacun validé avant activation), génère et
affiche **une seule fois** le jeton bearer à copier dans le dashboard,
installe les dépendances Python dans un environnement virtuel dédié, active
le service systemd `tpagent`, et enfin propose de sécuriser le compte
administrateur du SGBD. Détail complet de chaque étape :
[install-agent-on-vm.md](install-agent-on-vm.md#2-lancer-linstallateur).

**Copiez le jeton bearer affiché** — il ne sera plus jamais montré en clair
(il reste lisible ensuite dans `/opt/tpagent/tpagent.env`, mode `600`,
propriétaire `tpagent`, si besoin de le retrouver).

Vérification :

```bash
curl -s http://localhost:8443/health
# {"status":"ok","uptimeSeconds":...}
```

**Si `systemctl status tpagent` échoue avec `Failed to set up mount
namespacing`** : vous installez depuis une version du dépôt antérieure au
retrait du durcissement systemd (`ProtectSystem=strict` + `ReadWritePaths`
incompatibles avec les scripts privilégiés lancés via `sudo`, voir le
commentaire dans `agent/packaging/tpagent.service`) — mettez à jour le dépôt
plutôt que de contourner le problème.

## 4. Installer les paquets requis par le dashboard

Le dashboard est une application Symfony/PHP qui utilise SQLite pour son
propre stockage (établissements, classes, élèves, projets — indépendamment
du moteur choisi à l'étape 2 pour les bases des élèves) :

```bash
sudo apt-get install -y php php-cli php-sqlite3 php-intl php-mbstring \
    php-xml php-curl composer unzip
```

`php-sqlite3` et `php-intl` sont **confirmés indispensables** (sans eux, la
migration de la base échoue avec `could not find driver`, ou l'installateur
Composer échoue plus tôt) ; `php-mbstring`, `php-xml` et `php-curl` sont des
prérequis usuels d'une application Symfony, à installer par prudence même
si `composer.json` ne les déclare pas explicitement.

## 5. Déployer le dashboard

```bash
sudo mkdir -p /opt/tpdashboard
sudo cp -r /tmp/tp-manager/dashboard/. /opt/tpdashboard/
```

Configuration de production, dans un `.env.local` **non versionné** (ne
modifiez jamais le `.env` du dépôt pour ça) :

```bash
sudo tee /opt/tpdashboard/.env.local > /dev/null <<EOF
APP_ENV=prod
APP_SECRET=$(openssl rand -hex 16)
EOF
```

`composer install` **doit être exécuté en tant que `www-data`, pas en
`root`** : Composer désactive automatiquement ses plugins (dont
`cache:clear`/`assets:install`) dès qu'il détecte qu'il tourne en `root`, et
l'échec est silencieux.

```bash
sudo chown -R www-data:www-data /opt/tpdashboard
sudo -u www-data env COMPOSER_HOME=/tmp/composer-home composer install \
    --no-dev --optimize-autoloader --no-interaction --working-dir=/opt/tpdashboard

sudo -u www-data php /opt/tpdashboard/bin/console doctrine:migrations:migrate --no-interaction
```

(`COMPOSER_HOME` pointe vers un dossier temporaire accessible : `www-data`
n'a pas de `$HOME` par défaut sur une installation Debian standard.)

## 6. Mettre le dashboard en service

Le guide utilisateur ([../guide-utilisateur.md](../guide-utilisateur.md))
suggère `php -S 0.0.0.0:8000 -t public` pour un usage simple. Pour qu'il
survive à la déconnexion SSH et à un redémarrage, encapsulez-le dans un
service systemd plutôt que de le laisser tourner au premier plan — ce dépôt
ne fournit pas encore de fichier unité packagé pour le dashboard (seul
l'agent en a un), créez-le donc directement :

```bash
sudo tee /etc/systemd/system/tpdashboard.service > /dev/null <<'EOF'
[Unit]
Description=TP Manager - dashboard (Symfony, serveur integre PHP)
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/opt/tpdashboard
Environment=APP_ENV=prod
ExecStart=/usr/bin/php -S 0.0.0.0:8000 -t public
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now tpdashboard
```

Vérification :

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8000/
# 302, vers /etablissement (assistant de configuration) : c'est le comportement attendu
```

## 7. Vérifier `web_root_base` avant le premier élève provisionné

`web_root_base` (`/var/www/html` par défaut, saisi à l'étape 1 de
l'assistant de configuration) sert de racine aux chroots SFTP des élèves.
OpenSSH exige que ce dossier **et tous ses parents** soient possédés par
`root` et non inscriptibles par groupe/autre — sinon la connexion SFTP de
l'élève échoue avec `Software caused connection abort`, un symptôme qui
n'apparaît qu'au premier provisioning réel si on ne vérifie pas avant :

```bash
stat -c '%U:%G %a' / /var /var/www /var/www/html
# chaque ligne doit afficher : root:root suivi de 755 (ou plus restrictif) —
# aucune ne doit être inscriptible par le groupe ou par tout le monde (777, 775...)
```

Corrigez tout dossier fautif avec `chmod`/`chown` avant de provisionner le
premier élève. Ni `install.sh` ni les scripts de l'agent ne touchent aux
droits de `web_root_base` lui-même (seulement à ceux des sous-dossiers qu'ils
créent) — cette vérification n'est donc automatisée nulle part.

## 8. Configuration initiale du dashboard

Ouvrez `http://<ip-de-la-vm>:8000/` dans un navigateur : l'assistant de
configuration en 2 étapes (établissement, puis agent) s'affiche
automatiquement. Détail complet des deux étapes : section 4 de
[../guide-utilisateur.md](../guide-utilisateur.md).

Un seul point à respecter puisque l'agent et le dashboard tournent ici sur la
même machine : à l'étape 2, saisissez `http://localhost:8443` comme URL de
l'agent, **pas** l'IP publique de la VM — le jeton bearer transiterait sinon
sur l'interface réseau (même en boucle vers soi-même), pour aucun bénéfice
puisque les deux services sont co-localisés.

## Récapitulatif des vérifications finales

| Vérification | Commande |
|---|---|
| Agent actif | `systemctl is-active tpagent` → `active` |
| Agent répond | `curl -s http://localhost:8443/health` → `{"status":"ok",...}` |
| Dashboard actif | `systemctl is-active tpdashboard` → `active` |
| Dashboard répond | `curl -s -o /dev/null -w '%{http_code}' http://localhost:8000/` → `302` |
| `sshd` valide | `sudo sshd -t` (silencieux si OK) |
| `sudoers` valide | `sudo visudo -c` |
| `web_root_base` correctement protégé | voir étape 7 |

À ce stade, le serveur est prêt pour l'assistant de configuration puis la
création de classes/élèves/projets — suite du parcours dans
[../guide-utilisateur.md](../guide-utilisateur.md).
