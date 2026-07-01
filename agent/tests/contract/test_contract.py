import json
import uuid
from pathlib import Path
from unittest.mock import patch

import pytest
import yaml
from fastapi.testclient import TestClient

from tpagent.main import app

CONTRACT_PATH = Path(__file__).resolve().parents[3] / "contracts" / "openapi.yaml"
EXAMPLES_DIR = Path(__file__).resolve().parents[3] / "contracts" / "examples"

AUTH_HEADERS = {"Authorization": "Bearer test-token"}


def _load_schema(name: str) -> dict:
    spec = yaml.safe_load(CONTRACT_PATH.read_text(encoding="utf-8"))
    return spec["components"]["schemas"][name]


def _assert_matches_schema(body: dict, schema_name: str) -> None:
    schema = _load_schema(schema_name)
    for field in schema.get("required", []):
        assert field in body, f'Champ requis "{field}" manquant (schéma {schema_name}) : {body!r}'


def test_health_matches_contract():
    response = TestClient(app).get("/health")
    assert response.status_code == 200
    _assert_matches_schema(response.json(), "HealthResponse")


def test_config_requires_auth():
    response = TestClient(app).get("/v1/config")
    assert response.status_code == 401


def test_config_matches_contract():
    response = TestClient(app).get("/v1/config", headers=AUTH_HEADERS)
    assert response.status_code == 200
    _assert_matches_schema(response.json(), "ConfigResponse")


def test_create_linux_account_matches_contract():
    with patch("tpagent.api.linux_accounts.create_linux_account", return_value=(2001, False)):
        response = TestClient(app).post(
            "/v1/linux-accounts",
            headers=AUTH_HEADERS,
            json={
                "requestId": str(uuid.uuid4()),
                "username": "dupont2",
                "homeDir": "/var/www/html/dupont2",
                "authMethod": "password",
                "password": "Xk29fQm3Lp8rTz41",
            },
        )

    assert response.status_code == 201
    _assert_matches_schema(response.json(), "LinuxAccountResponse")


def test_create_database_matches_contract():
    with patch("tpagent.api.databases.get_provisioner") as mock_get_provisioner:
        mock_get_provisioner.return_value.engine_name = "mysql"
        mock_get_provisioner.return_value.create_database_and_user.return_value = False

        response = TestClient(app).post(
            "/v1/databases",
            headers=AUTH_HEADERS,
            json={
                "requestId": str(uuid.uuid4()),
                "dbName": "dupont2_sitevitrine",
                "dbUser": "dupont2_sitevitrine",
                "dbPassword": "Qm3Lp8rTz41Xk29f",
            },
        )

    assert response.status_code == 201
    _assert_matches_schema(response.json(), "DatabaseResponse")


def test_create_webroot_matches_contract():
    with patch(
        "tpagent.api.webroots.create_webroot",
        return_value=("/var/www/html/dupont2/site-vitrine", False),
    ):
        response = TestClient(app).post(
            "/v1/webroots",
            headers=AUTH_HEADERS,
            json={
                "requestId": str(uuid.uuid4()),
                "eleveLogin": "dupont2",
                "projetSlug": "site-vitrine",
                "owner": "dupont2",
                "group": "www-data",
            },
        )

    assert response.status_code == 201
    _assert_matches_schema(response.json(), "WebrootResponse")


def test_replaying_same_request_id_returns_cached_response_without_recalling_service():
    request_id = str(uuid.uuid4())
    payload = {
        "requestId": request_id,
        "eleveLogin": "martin3",
        "projetSlug": "app-web",
        "owner": "martin3",
        "group": "www-data",
    }

    with patch(
        "tpagent.api.webroots.create_webroot",
        return_value=("/var/www/html/martin3/app-web", False),
    ) as mock_create:
        client = TestClient(app)
        first = client.post("/v1/webroots", headers=AUTH_HEADERS, json=payload)
        second = client.post("/v1/webroots", headers=AUTH_HEADERS, json=payload)

    assert first.status_code == 201
    assert second.status_code == 201
    assert first.json() == second.json()
    mock_create.assert_called_once()


@pytest.mark.parametrize(
    "filename,request_schema,response_schema",
    [
        ("linux-user-create.json", "LinuxAccountCreateRequest", "LinuxAccountResponse"),
        ("db-provision-mysql.json", "DatabaseCreateRequest", "DatabaseResponse"),
        ("webroot-create.json", "WebrootCreateRequest", "WebrootResponse"),
    ],
)
def test_examples_match_contract_schemas(filename, request_schema, response_schema):
    example = json.loads((EXAMPLES_DIR / filename).read_text(encoding="utf-8"))
    _assert_matches_schema(example["request"], request_schema)
    _assert_matches_schema(example["response"], response_schema)
