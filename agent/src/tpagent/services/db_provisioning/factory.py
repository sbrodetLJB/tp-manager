from tpagent.domain.errors import NotImplementedEngineError
from tpagent.services.db_provisioning.base import DbProvisioner
from tpagent.services.db_provisioning.mysql_provisioner import MysqlProvisioner
from tpagent.services.db_provisioning.postgres_provisioner import PostgresProvisioner


def get_provisioner(db_engine: str) -> DbProvisioner:
    if db_engine == "mysql":
        return MysqlProvisioner()
    if db_engine == "postgresql":
        return PostgresProvisioner()

    raise NotImplementedEngineError(f'Moteur BDD inconnu : "{db_engine}".')
