# Installer l'agent sur une VM de TP réelle

## Prérequis

- VM Debian/Ubuntu avec accès root/sudo.
- `openssh-server` installé et actif.
- Moteur de base de données installé : `mariadb-server` **ou** `postgresql`
  (celui que vous compterez utiliser — voir
  [../decisions/0002-db-engine-abstraction.md](../decisions/0002-db-engine-abstraction.md)).
- Python 3.11+ (`python3 --version`).

## 1. Déployer le code de l'agent

```bash
git clone https://github.com/sbrodetLJB/tp-manager.git /tmp/tp-manager
sudo mkdir -p /opt/tpagent
sudo cp -r /tmp/tp-manager/agent/* /opt/tpagent/
```

`/opt/tpagent` est le chemin attendu par `install.sh` et par le fragment
sudoers (chemins fixes, voir [../security.md](../security.md)) — ne pas le
changer sans adapter `packaging/sudoers.d/tpagent` en conséquence.

## 2. Lancer l'installateur

```bash
sudo /opt/tpagent/packaging/install.sh
```

Étapes effectuées (idempotentes, peuvent être relancées sans risque) :

1. Création du groupe `tp-students` et de l'utilisateur système `tpagent`.
2. Verrouillage des scripts privilégiés (`root:root`, mode `0750`).
3. Installation du fragment sudoers (`visudo -c` obligatoire avant activation).
4. Installation de la configuration sshd du chroot SFTP (`sshd -t` obligatoire
   avant activation, puis rechargement du service).
5. **Génération du jeton bearer, affiché une seule fois** :

   ```
   [install.sh] Jeton bearer généré. À coller UNE FOIS dans l'assistant de configuration du dashboard :
   [install.sh]   3c4087316692...
   ```

   Copiez-le immédiatement — il n'est stocké que dans
   `/opt/tpagent/tpagent.env` (mode `600`, propriétaire `tpagent`) et ne sera
   plus jamais affiché en clair.
6. Installation des dépendances Python (venv dédié) et démarrage du service
   `tpagent.service` (si systemd est détecté).

## 3. Choisir le moteur de base de données

Définissez `TPAGENT_DB_ENGINE=mysql` (ou `postgresql`) dans
`/opt/tpagent/tpagent.env`, puis redémarrez le service :

```bash
echo "TPAGENT_DB_ENGINE=postgresql" | sudo tee -a /opt/tpagent/tpagent.env
sudo systemctl restart tpagent
```

## 4. Vérifier

```bash
curl -s http://localhost:8443/health
curl -s -H "Authorization: Bearer <jeton>" http://localhost:8443/v1/config
```

La deuxième commande doit renvoyer le moteur configuré à l'étape 3. C'est
exactement ce que fait l'assistant de configuration du dashboard à la
connexion de l'agent — si ça fonctionne en local sur la VM, la seule chose
restant à vérifier est l'accessibilité réseau depuis le poste du dashboard
(pare-feu, port).

## Dépannage

**"connection reset" en SFTP après provisioning d'un élève**
Presque toujours un problème d'ownership du chroot — voir la section
"Confinement SFTP" de [../security.md](../security.md). Vérifiez :
```bash
stat -c '%U:%G %a' /var/www/html/<login>
# doit afficher : root:root 755
```

**Le fragment sudoers ou la config sshd n'a pas été installé**
`install.sh` annule l'installation (et supprime le fichier) si `visudo -c` ou
`sshd -t` échoue — relisez le message d'erreur affiché, il indique la ligne
fautive.

**Le service ne démarre pas**
```bash
sudo journalctl -u tpagent -n 50
```
