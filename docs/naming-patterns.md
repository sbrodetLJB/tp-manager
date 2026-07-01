# Convention de nommage

Chaque établissement définit un ou plusieurs **gabarits de nommage**
(`NamingPattern`) — un seul actif à la fois — qui génère automatiquement le
login d'un élève (compte Linux + base de données) à partir de son nom/prénom.

## Jetons disponibles

| Jeton | Source | Exemple (Jean Dupont, matricule A123, classe 2025) |
|---|---|---|
| `{prenom}` | `Eleve::prenom` | `jean` |
| `{nom}` | `Eleve::nom` | `dupont` |
| `{initiale_prenom}` | 1ère lettre du prénom | `j` |
| `{initiale_nom}` | 1ère lettre du nom | `d` |
| `{matricule}` | `Eleve::matricule` (optionnel) | `a123` |
| `{annee}` | Année scolaire de la classe | `2025` |

Implémentation : `dashboard/src/Service/Naming/LoginPatternRenderer.php`.
Rendu en deux temps : (1) substitution des jetons par les valeurs brutes de
l'élève (accents conservés), (2) passage dans `LoginSanitizer` pour obtenir un
identifiant valide.

## Règles de normalisation (`LoginSanitizer`)

1. Translitération ASCII des caractères accentués français (table fixe, pas
   de fonction dépendante de la locale) : `é/è/ê/ë → e`, `ç → c`, `œ → oe`,
   etc.
2. Passage en minuscules.
3. Whitelist stricte `[a-z0-9_]` : toute suite d'un ou plusieurs caractères
   hors de cette liste (espace, point, tiret, apostrophe...) devient un
   **unique** underscore. C'est pourquoi le gabarit `{prenom}.{nom}` produit
   `jean_dupont` et non `jean.dupont` — le point n'est pas un caractère valide
   pour un identifiant Linux/SQL (voir [security.md](security.md)).
4. Un login ne peut pas commencer par un chiffre ou un underscore (contrainte
   des comptes Linux) : ces caractères de tête sont supprimés.
5. Troncature à `maxLength` (32 par défaut, limite POSIX des noms
   d'utilisateur Linux), avec suppression d'un éventuel underscore de fin
   laissé par la coupe.

## Résolution des collisions

Si le login généré existe déjà (établissement entier, pas seulement la
classe — un login correspond à un compte Linux sur une VM partagée),
`CollisionResolver` ajoute un suffixe numérique : `dupont`, puis `dupont2`,
`dupont3`, etc. Le suffixe est appliqué **après** troncature à `maxLength`,
en réservant la place nécessaire (ex: un login de 32 caractères tronqué à 31
avant d'ajouter le chiffre `2`), pour ne jamais dépasser la limite.

Deux homonymes exacts dans le **même import CSV** sont également détectés et
suffixés correctement (`StudentLoginAssigner` vérifie à la fois la base de
données et les logins déjà attribués plus tôt dans le même lot).

Le suffixe effectivement appliqué est conservé (`Eleve::loginSuffix`) à des
fins de traçabilité — utile pour comprendre après coup "pourquoi Dupont est
devenu dupont2".

## Aperçu avant activation

La page de création d'un gabarit calcule un aperçu en direct sur un
échantillon fictif contenant un doublon volontaire (`Dupont Jean`, `Martin
Paul` x2, `Lefèvre Amélie`), pour visualiser le résultat — y compris la
gestion des collisions — avant d'importer de vrais élèves.
