import re

from tpagent.domain.errors import PrivilegedScriptError
from tpagent.util.shell import run_privileged_script


def create_linux_account(username: str, home_dir: str, auth_method: str, secret: str) -> tuple:
    """Retourne (uid, already_existed)."""
    stdout, already_existed = run_privileged_script(
        "tpagent-create-linux-user.sh", "create", username, home_dir, auth_method, secret
    )

    match = re.search(r"uid=(\d+)", stdout)
    if not match:
        raise PrivilegedScriptError(f"Sortie inattendue du script de création de compte Linux : {stdout!r}")

    return int(match.group(1)), already_existed


def reset_linux_account_password(username: str, auth_method: str, secret: str) -> bool:
    """Change le secret d'authentification d'un compte existant, sans toucher
    au home ni aux fichiers déjà déposés par l'élève. Retourne True si le
    compte n'existait pas (à traiter comme 404 côté API)."""
    _stdout, not_found = run_privileged_script(
        "tpagent-create-linux-user.sh", "reset-password", username, auth_method, secret
    )
    return not_found


def delete_linux_account(username: str, purge_home: bool) -> bool:
    """Retourne True si le compte n'existait déjà plus (idempotent)."""
    _stdout, already_gone = run_privileged_script(
        "tpagent-delete-linux-user.sh", username, "true" if purge_home else "false"
    )
    return already_gone
