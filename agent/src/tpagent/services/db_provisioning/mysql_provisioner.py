from tpagent.services.db_provisioning.base import DbProvisioner
from tpagent.util.shell import run_privileged_script


class MysqlProvisioner(DbProvisioner):
    engine_name = "mysql"

    def create_database_and_user(self, db_name: str, db_user: str, db_password: str, charset: str) -> bool:
        _stdout, already_existed = run_privileged_script(
            "tpagent-mysql-provision.sh", db_name, db_user, db_password, charset
        )
        return already_existed

    def engine_version(self) -> str:
        # Sonde de version non implémentée en Phase 2 (aucun script sudo dédié
        # n'est prévu pour une opération en lecture seule) — n/a tant que
        # cette information n'est pas nécessaire au provisioning lui-même.
        return "unknown"
