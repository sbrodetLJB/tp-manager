# Sauvegarder et restaurer le dashboard

## Ce qui doit être sauvegardé

Le stockage propre du dashboard est un unique fichier SQLite :
`dashboard/var/data_prod.db` (ou `data_dev.db` en développement — voir
[../decisions/0003-sqlite-for-v1-persistence.md](../decisions/0003-sqlite-for-v1-persistence.md)).
Il contient l'établissement, les classes, élèves, projets et le journal de
provisioning — **pas** les bases de données des projets élèves elles-mêmes
(celles-ci vivent sur la VM de TP et doivent être sauvegardées séparément
avec vos outils habituels de sauvegarde MySQL/PostgreSQL).

Sauvegardez également le fichier `.env.local` du dashboard (contient
`APP_SECRET`, utilisé pour chiffrer le jeton agent — voir
[../security.md](../security.md)) : sans lui, les jetons/identifiants chiffrés
stockés deviennent indéchiffrables après restauration.

## Sauvegarde

```bash
cp dashboard/var/data_prod.db /chemin/de/sauvegarde/data_prod-$(date +%Y%m%d).db
cp dashboard/.env.local /chemin/de/sauvegarde/env.local-$(date +%Y%m%d)
```

Automatisez ceci via une tâche cron régulière sur le poste hébergeant le
dashboard.

## Restauration

```bash
cp /chemin/de/sauvegarde/data_prod-20260615.db dashboard/var/data_prod.db
cp /chemin/de/sauvegarde/env.local-20260615 dashboard/.env.local
cd dashboard && php bin/console doctrine:migrations:migrate --no-interaction
```

La dernière commande applique toute migration de schéma sortie depuis la
sauvegarde (sans effet si le schéma est déjà à jour).

## Si `APP_SECRET` a changé (sauvegarde de `.env.local` perdue)

Le jeton agent stocké et les éventuels identifiants `CredentialReveal` non
consultés deviennent indéchiffrables (voir
[../security.md](../security.md)). Il faut alors :
1. Reconnecter l'agent avec un jeton régénéré — voir
   [rotate-agent-token.md](rotate-agent-token.md) ;
2. Considérer comme perdus les identifiants de provisioning non encore
   consultés (redistribuer via "Forcer le nettoyage" puis "Réessayer le
   provisioning" sur les projets concernés, qui régénère de nouveaux
   identifiants).
