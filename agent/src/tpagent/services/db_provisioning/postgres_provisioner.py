from tpagent.services.db_provisioning.base import DbProvisioner
from tpagent.util.shell import run_privileged_script


class PostgresProvisioner(DbProvisioner):
    engine_name = "postgresql"

    def create_database_and_user(self, db_name: str, db_user: str, db_password: str, charset: str) -> bool:
        # charset ignoré : PostgreSQL utilise l'encodage de la base template
        # (UTF8 par défaut), pas de paramètre équivalent par rôle/base ici.
        _stdout, already_existed = run_privileged_script(
            "tpagent-postgres-provision.sh", "create", db_name, db_user, db_password
        )
        return already_existed

    def drop_database_and_user(self, db_name: str, db_user: str) -> bool:
        _stdout, already_gone = run_privileged_script("tpagent-postgres-provision.sh", "drop", db_name, db_user)
        return already_gone

    def reset_password(self, db_name: str, db_user: str, db_password: str) -> bool:
        _stdout, not_found = run_privileged_script(
            "tpagent-postgres-provision.sh", "reset-password", db_name, db_user, db_password
        )
        return not_found

    def engine_version(self) -> str:
        return "unknown"
