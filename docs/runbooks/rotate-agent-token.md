# Faire tourner le jeton de l'agent

Le jeton bearer (`AgentConnection::bearerTokenEncrypted`) authentifie le
dashboard auprès de l'agent. `install.sh` ne le régénère jamais silencieusement
— voici la procédure explicite pour le faire tourner.

## Procédure normale

1. Sur la VM de TP, générer un nouveau jeton et l'écrire dans
   `/opt/tpagent/tpagent.env` :

   ```bash
   NEW_TOKEN=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
   sudo sed -i "s/^TPAGENT_BEARER_TOKEN=.*/TPAGENT_BEARER_TOKEN=$NEW_TOKEN/" /opt/tpagent/tpagent.env
   sudo systemctl restart tpagent
   echo "Nouveau jeton : $NEW_TOKEN"
   ```

2. **L'ancien jeton est immédiatement invalide** dès le redémarrage du
   service — le dashboard ne pourra plus joindre l'agent jusqu'à l'étape 3.

3. Dans le dashboard : **Établissement → Configurer la connexion à l'agent**,
   coller le nouveau jeton, enregistrer. La vérification `/v1/config` doit
   repasser au vert immédiatement.

## Récupération en cas d'échec partiel

Si l'étape 3 échoue (dashboard non mis à jour après le redémarrage de
l'agent) : le dashboard reste bloqué avec l'ancien jeton, désormais invalide.
Répéter simplement l'étape 3 avec le jeton généré à l'étape 1 (il reste
valide côté agent jusqu'à la prochaine rotation) — aucune perte de
configuration, l'établissement et les classes/élèves/projets existants ne
sont pas affectés, seule la connexion agent doit être remise à jour.

## Pourquoi pas une rotation automatique dashboard → agent ?

Un flux self-service (le dashboard génère et pousse lui-même le nouveau
jeton) a été envisagé mais volontairement laissé hors V1 : si l'étape "pousser
le nouveau jeton" réussit côté agent mais échoue à revenir confirmer côté
dashboard, les deux composants se retrouvent désynchronisés sans que
l'enseignant ait un jeton de secours à saisir manuellement. La procédure
manuelle ci-dessus reste imparfaite mais toujours récupérable par un humain.
