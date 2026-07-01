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
    def drop_database_and_user(self, db_name: str, db_user: str) -> bool:
        """Supprime la base + l'utilisateur. Retourne True s'ils n'existaient déjà plus (idempotent)."""
        raise NotImplementedError

    @abstractmethod
    def reset_password(self, db_name: str, db_user: str, db_password: str) -> bool:
        """Change le mot de passe de l'utilisateur, sans toucher à la base ni
        aux GRANT existants. Retourne True si l'utilisateur n'existait pas
        (à traiter comme 404 côté API)."""
        raise NotImplementedError

    @abstractmethod
    def engine_version(self) -> str:
        raise NotImplementedError
