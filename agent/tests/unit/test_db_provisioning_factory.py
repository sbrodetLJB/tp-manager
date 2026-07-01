import pytest

from tpagent.domain.errors import NotImplementedEngineError
from tpagent.services.db_provisioning.factory import get_provisioner
from tpagent.services.db_provisioning.mysql_provisioner import MysqlProvisioner
from tpagent.services.db_provisioning.postgres_provisioner import PostgresProvisioner


def test_returns_mysql_provisioner():
    assert isinstance(get_provisioner("mysql"), MysqlProvisioner)


def test_returns_postgres_provisioner():
    assert isinstance(get_provisioner("postgresql"), PostgresProvisioner)


def test_raises_for_unknown_engine():
    with pytest.raises(NotImplementedEngineError):
        get_provisioner("oracle")
