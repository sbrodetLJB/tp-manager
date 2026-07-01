class AgentError(Exception):
    """Erreur métier de l'agent, mappée en réponse HTTP {errorCode, message}."""

    status_code: int = 500
    error_code: str = "INTERNAL_ERROR"

    def __init__(self, message: str) -> None:
        super().__init__(message)
        self.message = message


class InvalidIdentifierError(AgentError):
    status_code = 422
    error_code = "INVALID_IDENTIFIER"


class AlreadyExistsError(AgentError):
    """Levée uniquement quand une ressource existe déjà avec des paramètres différents.

    Une re-création avec les MÊMES paramètres est traitée comme idempotente
    (voir job_ledger) et ne lève pas cette erreur.
    """

    status_code = 409
    error_code = "ALREADY_EXISTS"


class EngineUnavailableError(AgentError):
    status_code = 500
    error_code = "DB_ENGINE_UNAVAILABLE"


class NotImplementedEngineError(AgentError):
    status_code = 500
    error_code = "DB_ENGINE_NOT_IMPLEMENTED"


class PrivilegedScriptError(AgentError):
    status_code = 500
    error_code = "PRIVILEGED_SCRIPT_ERROR"
