from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

from tpagent.api import databases, health, linux_accounts, webroots
from tpagent.domain.errors import AgentError
from tpagent.services.job_ledger import RequestIdReusedWithDifferentPayloadError


def create_app() -> FastAPI:
    app = FastAPI(title="tpagent", version="0.1.0")
    app.include_router(health.router)
    app.include_router(linux_accounts.router)
    app.include_router(databases.router)
    app.include_router(webroots.router)

    @app.exception_handler(AgentError)
    def handle_agent_error(_request: Request, exc: AgentError) -> JSONResponse:
        return JSONResponse(
            status_code=exc.status_code,
            content={"errorCode": exc.error_code, "message": exc.message},
        )

    @app.exception_handler(RequestIdReusedWithDifferentPayloadError)
    def handle_request_id_reused(_request: Request, exc: RequestIdReusedWithDifferentPayloadError) -> JSONResponse:
        return JSONResponse(
            status_code=409,
            content={"errorCode": "REQUEST_ID_REUSED", "message": str(exc)},
        )

    return app


app = create_app()
