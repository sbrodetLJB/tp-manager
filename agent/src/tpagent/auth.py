from fastapi import Header, HTTPException

from tpagent.config import settings


def verify_bearer_token(authorization: str = Header(default="")) -> None:
    expected = f"Bearer {settings.bearer_token}"

    if not settings.bearer_token or authorization != expected:
        raise HTTPException(
            status_code=401,
            detail={"errorCode": "UNAUTHORIZED", "message": "Bearer token manquant ou invalide."},
        )
