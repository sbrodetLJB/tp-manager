import subprocess
from pathlib import Path
from typing import Tuple

from tpagent.config import settings
from tpagent.domain.errors import AlreadyExistsError, PrivilegedScriptError

# Convention de code de sortie partagée avec agent/scripts/*.sh :
#   0 -> créé  |  2 -> idempotent (déjà existant, paramètres identiques)
#   3 -> conflit (déjà existant, paramètres différents)  |  autre -> erreur
EXIT_CREATED = 0
EXIT_ALREADY_EXISTS = 2
EXIT_CONFLICT = 3


def run_privileged_script(script_name: str, *args: str) -> Tuple[str, bool]:
    """Exécute un script privilégié via sudo à chemin fixe (jamais de shell=True,
    jamais d'interpolation de commande). Retourne (stdout, already_existed).
    """
    script_path = str(Path(settings.scripts_dir) / script_name)

    result = subprocess.run(
        ["sudo", "-n", script_path, *args],
        capture_output=True,
        text=True,
        timeout=30,
    )

    if result.returncode == EXIT_CREATED:
        return result.stdout.strip(), False
    if result.returncode == EXIT_ALREADY_EXISTS:
        return result.stdout.strip(), True
    if result.returncode == EXIT_CONFLICT:
        raise AlreadyExistsError(
            result.stderr.strip() or "La ressource existe déjà avec des paramètres différents."
        )

    raise PrivilegedScriptError(
        f"Échec du script {script_name} (code {result.returncode}) : {result.stderr.strip()}"
    )
