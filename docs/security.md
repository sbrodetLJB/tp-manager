# Sécurité

Ce document formalise le modèle de sécurité de TP Manager. Le code source
reste la référence exacte ; les fichiers cités ici sont les points d'entrée à
relire en cas de doute.

## Frontière de confiance

Le **dashboard** n'a et n'a besoin d'aucun privilège système. Il ne se
connecte jamais en SSH à la VM de TP et ne stocke jamais de mot de passe
système ou de base de données en clair. Toute action à privilège transite par
l'**agent**, qui est le seul composant à détenir des droits root (via sudo
restreint) sur la VM de TP.

## Moindre privilège de l'agent

L'agent tourne comme un utilisateur système dédié `tpagent`
(`/usr/sbin/nologin`, créé par `agent/packaging/install.sh`), jamais comme
root directement. `/etc/sudoers.d/tpagent` (voir
`agent/packaging/sudoers.d/tpagent`) l'autorise à exécuter, sans mot de passe,
**exactement 6 scripts à chemin fixe** :

```
tpagent-create-linux-user.sh   tpagent-delete-linux-user.sh
tpagent-create-webroot.sh      tpagent-delete-webroot.sh
tpagent-mysql-provision.sh     tpagent-postgres-provision.sh
```

Sudoers ne peut restreindre que l'exécutable, pas ses arguments : chaque
script revalide donc lui-même ses arguments (voir `agent/scripts/lib/common.sh`),
indépendamment de la validation déjà faite côté API Python — défense en
profondeur, pas une simple UX.

Le service `tpagent.service` (systemd, sur une vraie VM) ajoute un
durcissement supplémentaire : `NoNewPrivileges`, `ProtectSystem=strict`,
`ProtectHome=true`, `PrivateTmp=true`, et un `ReadWritePaths` limité au strict
nécessaire (journal d'idempotence, logs). Un RCE dans le process FastAPI reste
ainsi borné aux 6 scripts listés, sans accès direct au reste du système.

Chacun de ces scripts expose une action `reset-password` en plus de
`create`/`drop` (`tpagent-create-linux-user.sh`, `tpagent-mysql-provision.sh`,
`tpagent-postgres-provision.sh`) : elle change uniquement le secret
d'authentification (mot de passe/clé publique côté Linux, `ALTER
USER`/`ALTER ROLE` côté BDD) d'un compte **déjà existant**, sans jamais
toucher au home, aux fichiers déjà déposés, à la base ou aux GRANT déjà
accordés. C'est le seul moyen sûr de récupérer un projet dont l'élève a perdu
son mot de passe — l'alternative (déprovisionner puis reprovisionner)
détruirait ses données. Voir `ProjectCredentialResetService` côté dashboard
et le bouton "Réinitialiser les identifiants" sur la page d'un projet.

## Anti-injection

Deux couches distinctes, avec des rôles différents :

- **Côté dashboard** (`LoginSanitizer`, `ProjectSlugSanitizer`) : confort UX
  uniquement — génère des logins/slugs propres, mais n'est pas la garantie de
  sécurité.
- **Côté agent** (`agent/src/tpagent/util/sanitize.py`) : frontière de
  confiance réelle. Whitelist stricte :
  - identifiants Linux/SQL : `^[a-z][a-z0-9_]*$`, 32 caractères max (Linux),
    63 (MySQL/PostgreSQL) ;
  - segments de chemin (`projetSlug`) : `^[a-z0-9][a-z0-9_-]*$` (le tiret est
    autorisé, contrairement aux identifiants Linux/SQL).

Aucune commande shell n'est jamais construite par interpolation de chaîne
côté Python (`subprocess.run([...], shell=False)`, liste d'arguments). Côté
scripts shell, les identifiants sont validés par regex **avant** toute
utilisation dans une commande `mysql`/`psql`/`useradd` — c'est le seul
endroit où le modèle est "on sanitize puis on fait confiance", précisément
parce que ni MySQL ni PostgreSQL n'acceptent de paramètres liés pour un nom
d'objet (`CREATE DATABASE`, `CREATE USER`...). Les mots de passe (qui ne sont
pas des identifiants et peuvent contenir n'importe quel caractère imprimable)
sont échappés avant insertion dans le littéral SQL (`sed` sur `\` et `'`) —
défense en profondeur, le générateur côté dashboard (`CredentialGenerator`)
ne produit de toute façon que des caractères alphanumériques.

Le anti path-traversal pour les webroots est vérifié une seconde fois via
`realpath`/`readlink -f` : même si `eleveLogin`/`projetSlug` ont déjà passé la
whitelist, le chemin résolu final doit rester sous `webRootBase`.

## Confinement SFTP (chroot)

Chaque compte élève est ajouté au groupe système `tp-students`. Un bloc
`Match Group tp-students` dans `agent/packaging/sshd_config.d/tpagent-sftp.conf`
restreint ces comptes à `internal-sftp` (pas de shell, pas de forwarding TCP/X11)
et les enferme dans leur `ChrootDirectory` (`%h`, leur home).

OpenSSH exige que la racine du chroot et tous ses parents soient `root:root`,
non modifiables par le groupe/other — sans quoi il refuse silencieusement la
connexion (`connection reset`, sans message d'erreur clair côté client). Le
schéma appliqué par `tpagent-create-linux-user.sh`/`tpagent-create-webroot.sh` :

```
/var/www/html/<login>              root:root   0755   (racine du chroot, jamais modifiée)
/var/www/html/<login>/<projet>     <login>:www-data  2750   (seul dossier réellement accessible en écriture)
```

**Piège vérifié en pratique** : `userdel -r` refuse de supprimer un home qui
n'est pas possédé par l'utilisateur (précisément notre cas, par conception).
`tpagent-delete-linux-user.sh` supprime donc le compte et le home en deux
étapes séparées (`userdel` puis `rm -rf` fait par le script lui-même), pour
ne pas dépendre de cette vérification de `userdel`.

Vérification automatisée du confinement : `tools/sftp-jail-check.sh` (upload
dans son propre dossier autorisé, `cd ..` depuis la racine reste confiné,
lecture de `/etc/passwd` par chemin absolu refusée car inexistant dans le
chroot).

## Portée des privilèges sur la base de données de l'élève

`GRANT ALL PRIVILEGES ON <db>.* TO <user> WITH GRANT OPTION` (MySQL) donne à
l'élève tous les droits sur **sa seule base**, avec la possibilité de
déléguer (`GRANT`/`REVOKE`) des sous-ensembles de ces droits à des comptes
**déjà existants** — utile pour qu'il crée par exemple, avec l'aide de
l'enseignant, un compte applicatif en lecture seule pour son propre projet.

Ce que `WITH GRANT OPTION` permet et ne permet pas (vérifié en pratique
contre le fake-vm) :

| Action | Résultat |
|---|---|
| Requêtes DDL/DML sur sa propre base | ✅ autorisé |
| `GRANT`/`REVOKE` d'un sous-ensemble de ses droits à un compte existant, sur sa propre base | ✅ autorisé (compte "lecture seule" vérifié fonctionnel : `SELECT` OK, `INSERT` refusé) |
| Accès à la base d'un **autre** projet du même élève | ❌ refusé (`Access denied ... to database`) |
| Accès à la base d'un **autre** élève | ❌ refusé |
| `CREATE USER` (créer un nouveau compte lui-même) | ❌ refusé — privilège global MySQL, non limitable à une seule base, volontairement pas accordé |

Le dernier point est une limite assumée : MySQL ne permet pas de scoper
`CREATE USER` à une base précise. L'accorder rendrait n'importe quel élève
capable de créer/supprimer des comptes affectant potentiellement l'ensemble
du serveur, cassant l'isolation entre élèves au niveau des *comptes* (même si
les *données* resteraient isolées). Si un établissement a besoin que les
élèves créent eux-mêmes des comptes secondaires, la marche à suivre reste que
l'enseignant (ou l'agent, via un mécanisme à ajouter si besoin) pré-crée les
comptes secondaires, que l'élève configure ensuite via `GRANT`.

PostgreSQL : le rôle propriétaire (`OWNER`) d'une base a nativement les mêmes
garanties (droits complets sur ses objets, avec possibilité de les déléguer à
des rôles existants) sans clause équivalente à ajouter — c'est une propriété
de la notion de propriétaire en PostgreSQL, pas une option à activer.

## Secrets

- **Jeton agent** (`AgentConnection::bearerTokenEncrypted`) : chiffré au repos
  (AES-256-GCM) via `App\Service\Security\AgentTokenEncryptor`, clé dérivée
  d'`APP_SECRET`. Limite connue documentée : une rotation d'`APP_SECRET`
  invalide les jetons stockés (il faut alors en régénérer un via
  [runbooks/rotate-agent-token.md](runbooks/rotate-agent-token.md)).
- **Mots de passe élèves/BDD** : jamais stockés en clair de façon permanente.
  Générés à la volée (`CredentialGenerator`, alphabet alphanumérique
  uniquement — évite tout besoin d'échappement complexe côté scripts),
  transmis à l'agent en HTTPS, puis chiffrés dans `CredentialReveal` jusqu'à
  la première consultation (`CredentialRevealTokenManager::reveal()` efface
  le ciphertext immédiatement après lecture — modèle "affichage unique", à la
  manière d'une clé d'accès cloud). Une commande
  (`app:credentials:purge-expired`) supprime les identifiants expirés jamais
  consultés.
- **Clé publique SSH d'un élève** : jamais persistée en base — seule son
  empreinte SHA256 l'est (`SshPublicKeyFingerprintCalculator`), à des fins de
  traçabilité/vérification.

## Idempotence et rejeu

Les endpoints `POST` de l'agent (création) portent un `requestId` (UUID)
vérifié contre un journal local (`job_ledger.sqlite`, séparé du stockage du
dashboard) : rejouer la même requête (ex: après un timeout réseau côté
dashboard) renvoie la réponse déjà enregistrée plutôt que de ré-exécuter
l'opération. Les endpoints `DELETE` sont idempotents par nature (supprimer
une ressource déjà absente renvoie 404, que le client traite comme un
succès no-op) — pas besoin de journal dédié pour ceux-là.

## CSRF

Toutes les actions de mutation du dashboard (formulaires Symfony et actions
HTML brutes comme "Activer un gabarit" ou "Provisionner") sont protégées par
un jeton CSRF (`framework.csrf_protection: true`, vérifié explicitement dans
les contrôleurs pour les formulaires non gérés par le composant Form).
