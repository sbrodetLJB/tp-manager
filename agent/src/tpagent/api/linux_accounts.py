from fastapi import APIRouter, Depends

from tpagent.auth import verify_bearer_token
from tpagent.domain.models import LinuxAccountCreateRequest, LinuxAccountResponse
from tpagent.services.job_ledger import JobLedger
from tpagent.services.linux_account_service import create_linux_account
from tpagent.util.sanitize import validate_linux_username

router = APIRouter(dependencies=[Depends(verify_bearer_token)])
_ledger = JobLedger()

_ENDPOINT = "POST /v1/linux-accounts"


@router.post("/v1/linux-accounts", status_code=201)
def post_linux_account(payload: LinuxAccountCreateRequest) -> LinuxAccountResponse:
    payload_dict = payload.model_dump()
    cached = _ledger.get_cached_response(payload.requestId, _ENDPOINT, payload_dict)
    if cached is not None:
        return LinuxAccountResponse(**cached["body"])

    validate_linux_username(payload.username, "username")

    secret = payload.password if payload.authMethod == "password" else (payload.publicKey or "")
    uid, already_existed = create_linux_account(payload.username, payload.homeDir, payload.authMethod, secret)

    response = LinuxAccountResponse(
        username=payload.username,
        uid=uid,
        homeDir=payload.homeDir,
        status="already_exists" if already_existed else "created",
    )
    _ledger.record(payload.requestId, _ENDPOINT, payload_dict, 201, response.model_dump())
    return response
