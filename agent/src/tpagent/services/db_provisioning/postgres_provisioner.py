from tpagent.services.db_provisioning.base import DbProvisioner


class PostgresProvisioner(DbProvisioner):
    """Implémentation complète prévue en Phase 3 (tpagent-postgres-provision.sh)."""

    engine_name = "postgresql"

    def create_database_and_user(self, db_name: str, db_user: str, db_password: str, charset: str) -> bool:
        raise NotImplementedError("Le support PostgreSQL arrive en Phase 3.")

    def engine_version(self) -> str:
        raise NotImplementedError("Le support PostgreSQL arrive en Phase 3.")
