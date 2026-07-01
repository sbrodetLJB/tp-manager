from fastapi import APIRouter, Depends

from tpagent.auth import verify_bearer_token
from tpagent.config import settings
from tpagent.domain.models import WebrootCreateRequest, WebrootResponse
from tpagent.services.job_ledger import JobLedger
from tpagent.services.webroot_service import create_webroot
from tpagent.util.sanitize import validate_linux_username, validate_path_segment

router = APIRouter(dependencies=[Depends(verify_bearer_token)])
_ledger = JobLedger()

_ENDPOINT = "POST /v1/webroots"


@router.post("/v1/webroots", status_code=201)
def post_webroot(payload: WebrootCreateRequest) -> WebrootResponse:
    payload_dict = payload.model_dump()
    cached = _ledger.get_cached_response(payload.requestId, _ENDPOINT, payload_dict)
    if cached is not None:
        return WebrootResponse(**cached["body"])

    validate_linux_username(payload.eleveLogin, "eleveLogin")
    validate_path_segment(payload.projetSlug, field_name="projetSlug")

    path, already_existed = create_webroot(
        payload.eleveLogin, payload.projetSlug, payload.owner, payload.group, settings.web_root_base
    )

    response = WebrootResponse(
        path=path,
        owner=payload.owner,
        group=payload.group,
        mode="2750",
        status="already_exists" if already_existed else "created",
    )
    _ledger.record(payload.requestId, _ENDPOINT, payload_dict, 201, response.model_dump())
    return response
