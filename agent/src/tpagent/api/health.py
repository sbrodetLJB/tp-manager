import socket
import time

from fastapi import APIRouter, Depends

from tpagent.auth import verify_bearer_token
from tpagent.config import settings
from tpagent.domain.models import ConfigResponse

router = APIRouter()

_started_at = time.monotonic()


@router.get("/health")
def get_health() -> dict:
    return {"status": "ok", "uptimeSeconds": round(time.monotonic() - _started_at)}


@router.get("/v1/config", dependencies=[Depends(verify_bearer_token)])
def get_config() -> ConfigResponse:
    return ConfigResponse(
        agentVersion="0.1.0",
        contractVersion=settings.contract_version,
        dbEngine=settings.db_engine,
        webRootBase=settings.web_root_base,
        sftpChrootStrategy=settings.sftp_chroot_strategy,
        hostname=socket.gethostname(),
    )
