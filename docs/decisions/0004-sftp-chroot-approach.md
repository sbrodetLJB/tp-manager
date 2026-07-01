# 0004 — Confinement SFTP par chroot OpenSSH natif

## Contexte

Chaque élève doit pouvoir déposer ses fichiers via SFTP en toute autonomie,
sans pouvoir lire ou modifier les fichiers d'un autre élève ni accéder au
reste du système.

## Décision

`internal-sftp` d'OpenSSH (pas de binaire `sftp-server` externe) combiné à
`ChrootDirectory %h` dans un bloc `Match Group tp-students`
(`agent/packaging/sshd_config.d/tpagent-sftp.conf`). Le home de chaque élève
(racine du chroot) est `root:root`, ses sous-dossiers de projet lui
appartiennent — voir le détail dans [security.md](../security.md).

## Alternatives envisagées

- **Conteneur/VM dédié par élève** : isolation plus forte, mais démesuré pour
  un simple dépôt de fichiers statiques/PHP, et beaucoup plus coûteux à
  provisionner/déprovisionner à l'échelle d'une classe.
- **Quotas/ACL sans chroot** (accès SFTP non confiné, permissions Unix
  seules) : plus simple à mettre en place, mais un élève verrait la structure
  du système de fichiers au-delà de son propre dossier même s'il ne peut pas
  y écrire — moins rassurant pédagogiquement et plus risqué en cas d'erreur
  de permissions.

## Conséquences

- Contrainte stricte d'ownership à respecter exactement (root:root sur la
  racine du chroot) — une erreur cause un refus de connexion silencieux côté
  client (`connection reset`), documenté explicitement pour éviter la
  confusion en dépannage.
- `userdel -r` ne peut pas être utilisé tel quel pour le nettoyage (voir le
  correctif détaillé dans [security.md](../security.md)) : conséquence
  directe et vérifiée en pratique de ce choix d'ownership.
