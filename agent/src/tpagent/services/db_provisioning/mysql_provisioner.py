from tpagent.services.db_provisioning.base import DbProvisioner
from tpagent.util.shell import run_privileged_script


class MysqlProvisioner(DbProvisioner):
    engine_name = "mysql"

    def create_database_and_user(self, db_name: str, db_user: str, db_password: str, charset: str) -> bool:
        _stdout, already_existed = run_privileged_script(
            "tpagent-mysql-provision.sh", "create", db_name, db_user, db_password, charset
        )
        return already_existed

    def drop_database_and_user(self, db_name: str, db_user: str) -> bool:
        _stdout, already_gone = run_privileged_script("tpagent-mysql-provision.sh", "drop", db_name, db_user)
        return already_gone

    def reset_password(self, db_name: str, db_user: str, db_password: str) -> bool:
        _stdout, not_found = run_privileged_script(
            "tpagent-mysql-provision.sh", "reset-password", db_name, db_user, db_password
        )
        return not_found

    def engine_version(self) -> str:
        # Sonde de version non implémentée (aucun script sudo dédié n'est
        # prévu pour une opération en lecture seule) — n/a tant que cette
        # information n'est pas nécessaire au provisioning lui-même.
        return "unknown"
