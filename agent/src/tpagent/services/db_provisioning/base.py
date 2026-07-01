from abc import ABC, abstractmethod


class DbProvisioner(ABC):
    """Interface commune implémentée par chaque moteur BDD (MySQL, PostgreSQL),
    sélectionnée par factory.get_provisioner() selon la config de l'agent.
    """

    engine_name: str

    @abstractmethod
    def create_database_and_user(self, db_name: str, db_user: str, db_password: str, charset: str) -> bool:
        """Crée la base + l'utilisateur scoppé. Retourne True si déjà existants (idempotent)."""
        raise NotImplementedError

    @abstractmethod
    def engine_version(self) -> str:
        raise NotImplementedError
