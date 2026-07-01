from fastapi import APIRouter, Depends

from tpagent.auth import verify_bearer_token
from tpagent.config import settings
from tpagent.domain.errors import DatabaseNotFoundError
from tpagent.domain.models import DatabaseCreateRequest, DatabaseDeleteResponse, DatabaseResponse
from tpagent.services.db_provisioning.factory import get_provisioner
from tpagent.services.job_ledger import JobLedger
from tpagent.util.sanitize import validate_sql_identifier

router = APIRouter(dependencies=[Depends(verify_bearer_token)])
_ledger = JobLedger()

_ENDPOINT = "POST /v1/databases"


@router.post("/v1/databases", status_code=201)
def post_database(payload: DatabaseCreateRequest) -> DatabaseResponse:
    payload_dict = payload.model_dump()
    cached = _ledger.get_cached_response(payload.requestId, _ENDPOINT, payload_dict)
    if cached is not None:
        return DatabaseResponse(**cached["body"])

    validate_sql_identifier(payload.dbName, "dbName")
    validate_sql_identifier(payload.dbUser, "dbUser")

    provisioner = get_provisioner(settings.db_engine)
    already_existed = provisioner.create_database_and_user(
        payload.dbName, payload.dbUser, payload.dbPassword, payload.charset or "utf8mb4"
    )

    response = DatabaseResponse(
        engine=provisioner.engine_name,
        dbName=payload.dbName,
        dbUser=payload.dbUser,
        status="already_exists" if already_existed else "created",
    )
    _ledger.record(payload.requestId, _ENDPOINT, payload_dict, 201, response.model_dump())
    return response


@router.delete("/v1/databases/{dbName}")
def delete_database(dbName: str) -> DatabaseDeleteResponse:
    validate_sql_identifier(dbName, "dbName")

    # dbUser == dbName par convention (toujours vrai côté dashboard) : le
    # contrat DELETE ne transporte que dbName, voir contracts/openapi.yaml.
    provisioner = get_provisioner(settings.db_engine)
    already_gone = provisioner.drop_database_and_user(dbName, dbName)
    if already_gone:
        raise DatabaseNotFoundError(f'Base "{dbName}" introuvable (déjà supprimée).')

    return DatabaseDeleteResponse(dbName=dbName, status="deleted")
