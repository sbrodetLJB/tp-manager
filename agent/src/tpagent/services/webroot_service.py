import re

from tpagent.domain.errors import PrivilegedScriptError
from tpagent.util.shell import run_privileged_script


def create_webroot(eleve_login: str, projet_slug: str, owner: str, group: str, web_root_base: str) -> tuple:
    """Retourne (path, already_existed)."""
    stdout, already_existed = run_privileged_script(
        "tpagent-create-webroot.sh", eleve_login, projet_slug, owner, group, web_root_base
    )

    match = re.search(r"path=(.+)", stdout)
    if not match:
        raise PrivilegedScriptError(f"Sortie inattendue du script de création de webroot : {stdout!r}")

    return match.group(1).strip(), already_existed


def delete_webroot(eleve_login: str, projet_slug: str, web_root_base: str) -> bool:
    """Retourne True si le dossier n'existait déjà plus (idempotent)."""
    _stdout, already_gone = run_privileged_script(
        "tpagent-delete-webroot.sh", eleve_login, projet_slug, web_root_base
    )
    return already_gone
