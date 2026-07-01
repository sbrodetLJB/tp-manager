from fastapi import APIRouter, Depends

from tpagent.auth import verify_bearer_token
from tpagent.domain.errors import UserNotFoundError
from tpagent.domain.models import (
    LinuxAccountCreateRequest,
    LinuxAccountDeleteResponse,
    LinuxAccountPasswordResetRequest,
    LinuxAccountPasswordResetResponse,
    LinuxAccountResponse,
)
from tpagent.services.job_ledger import JobLedger
from tpagent.services.linux_account_service import create_linux_account, delete_linux_account, reset_linux_account_password
from tpagent.util.sanitize import validate_linux_username

router = APIRouter(dependencies=[Depends(verify_bearer_token)])
_ledger = JobLedger()

_ENDPOINT = "POST /v1/linux-accounts"
_RESET_ENDPOINT = "POST /v1/linux-accounts/{username}/reset-password"


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


@router.post("/v1/linux-accounts/{username}/reset-password")
def post_linux_account_reset_password(username: str, payload: LinuxAccountPasswordResetRequest) -> LinuxAccountPasswordResetResponse:
    validate_linux_username(username, "username")

    payload_dict = payload.model_dump()
    cached = _ledger.get_cached_response(payload.requestId, _RESET_ENDPOINT, payload_dict)
    if cached is not None:
        return LinuxAccountPasswordResetResponse(**cached["body"])

    secret = payload.password if payload.authMethod == "password" else (payload.publicKey or "")
    not_found = reset_linux_account_password(username, payload.authMethod, secret)
    if not_found:
        raise UserNotFoundError(f'Utilisateur "{username}" introuvable : impossible de réinitialiser son secret.')

    response = LinuxAccountPasswordResetResponse(username=username, status="reset")
    _ledger.record(payload.requestId, _RESET_ENDPOINT, payload_dict, 200, response.model_dump())
    return response


@router.delete("/v1/linux-accounts/{username}")
def delete_linux_account_endpoint(username: str, purgeHome: bool = False) -> LinuxAccountDeleteResponse:
    validate_linux_username(username, "username")

    already_gone = delete_linux_account(username, purgeHome)
    if already_gone:
        raise UserNotFoundError(f'Utilisateur "{username}" introuvable (déjà supprimé).')

    return LinuxAccountDeleteResponse(username=username, status="deleted")
