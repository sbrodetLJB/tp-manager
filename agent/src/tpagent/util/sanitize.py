import re

from tpagent.domain.errors import InvalidIdentifierError

# Frontière de confiance réelle (contrairement au sanitizer côté dashboard, qui
# n'est qu'un confort UX) : whitelist stricte [a-z0-9_], pas de tiret, pas de
# point — nécessaire pour les identifiants Linux ET SQL (aucun des deux
# n'accepte le tiret sans guillemets/échappement particulier).
_IDENTIFIER_PATTERN = re.compile(r"^[a-z][a-z0-9_]*$")

# projetSlug n'est ni un identifiant Linux ni SQL : c'est un simple segment de
# chemin sous /var/www/html/<login>/, où le tiret est courant et sûr
# (ex: "site-vitrine"). Toujours pas de point/slash pour éviter tout
# path-traversal, même si le webroot service revérifie aussi via realpath.
_PATH_SEGMENT_PATTERN = re.compile(r"^[a-z0-9][a-z0-9_-]*$")

LINUX_USERNAME_MAX_LENGTH = 32
SQL_IDENTIFIER_MAX_LENGTH = 63
PROJET_SLUG_MAX_LENGTH = 100


def validate_identifier(value: str, max_length: int, field_name: str) -> None:
    if not value or len(value) > max_length or not _IDENTIFIER_PATTERN.match(value):
        raise InvalidIdentifierError(
            f'Identifiant invalide pour "{field_name}" : "{value}" '
            f"(attendu : lettres minuscules/chiffres/underscore, débute par une lettre, "
            f"{max_length} caractères max)."
        )


def validate_linux_username(value: str, field_name: str = "username") -> None:
    validate_identifier(value, LINUX_USERNAME_MAX_LENGTH, field_name)


def validate_sql_identifier(value: str, field_name: str = "dbName") -> None:
    validate_identifier(value, SQL_IDENTIFIER_MAX_LENGTH, field_name)


def validate_path_segment(value: str, max_length: int = PROJET_SLUG_MAX_LENGTH, field_name: str = "projetSlug") -> None:
    if not value or len(value) > max_length or not _PATH_SEGMENT_PATTERN.match(value):
        raise InvalidIdentifierError(
            f'Segment de chemin invalide pour "{field_name}" : "{value}" '
            f"(attendu : lettres minuscules/chiffres/underscore/tiret, débute par un alphanumérique, "
            f"{max_length} caractères max)."
        )
