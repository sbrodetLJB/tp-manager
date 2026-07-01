#!/bin/sh
# Fonctions partagées par les scripts privilégiés tpagent-*.sh.
# Convention de code de sortie (voir agent/src/tpagent/util/shell.py) :
#   0 = créé | 2 = idempotent (déjà existant, paramètres identiques)
#   3 = conflit (déjà existant, état incohérent) | 1 = erreur générique

log() {
    printf '[tpagent] %s\n' "$*" >&2
}

# Revalidation indépendante de celle du dashboard/de l'API Python — défense en
# profondeur : sudoers ne peut pas restreindre la valeur des arguments, donc
# chaque script doit se protéger lui-même.
validate_identifier() {
    value="$1"
    max_length="$2"
    field="$3"

    case "$value" in
        [a-z]*) ;;
        *) log "Identifiant invalide pour $field : \"$value\" (doit commencer par une lettre minuscule)"; exit 1 ;;
    esac

    case "$value" in
        *[!a-z0-9_]*) log "Identifiant invalide pour $field : \"$value\" (caractères autorisés : a-z 0-9 _)"; exit 1 ;;
    esac

    length=$(printf '%s' "$value" | wc -c)
    if [ "$length" -gt "$max_length" ]; then
        log "Identifiant trop long pour $field : \"$value\" (max $max_length)"
        exit 1
    fi
}

# projetSlug n'est ni un identifiant Linux ni SQL : simple segment de chemin
# sous /var/www/html/<login>/, où le tiret est courant et sûr (ex: "site-vitrine").
validate_path_segment() {
    value="$1"
    max_length="$2"
    field="$3"

    case "$value" in
        [a-z0-9]*) ;;
        *) log "Segment de chemin invalide pour $field : \"$value\""; exit 1 ;;
    esac

    case "$value" in
        *[!a-z0-9_-]*) log "Segment de chemin invalide pour $field : \"$value\""; exit 1 ;;
    esac

    length=$(printf '%s' "$value" | wc -c)
    if [ "$length" -gt "$max_length" ]; then
        log "Segment de chemin trop long pour $field : \"$value\" (max $max_length)"
        exit 1
    fi
}
