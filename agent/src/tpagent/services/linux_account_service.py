import re

from tpagent.domain.errors import PrivilegedScriptError
from tpagent.util.shell import run_privileged_script


def create_linux_account(username: str, home_dir: str, auth_method: str, secret: str) -> tuple:
    """Retourne (uid, already_existed)."""
    stdout, already_existed = run_privileged_script(
        "tpagent-create-linux-user.sh", username, home_dir, auth_method, secret
    )

    match = re.search(r"uid=(\d+)", stdout)
    if not match:
        raise PrivilegedScriptError(f"Sortie inattendue du script de création de compte Linux : {stdout!r}")

    return int(match.group(1)), already_existed
