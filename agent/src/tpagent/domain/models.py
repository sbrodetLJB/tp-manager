from typing import Literal, Optional

from pydantic import BaseModel


class ConfigResponse(BaseModel):
    agentVersion: str
    contractVersion: str
    dbEngine: str
    dbEngineVersion: Optional[str] = None
    webRootBase: str
    sftpChrootStrategy: Optional[str] = None
    hostname: Optional[str] = None


class LinuxAccountCreateRequest(BaseModel):
    requestId: str
    username: str
    homeDir: str
    authMethod: Literal["password", "public_key"]
    password: Optional[str] = None
    publicKey: Optional[str] = None
    uidHint: Optional[int] = None


class LinuxAccountResponse(BaseModel):
    username: str
    uid: int
    homeDir: str
    status: Literal["created", "already_exists"]


class DatabaseCreateRequest(BaseModel):
    requestId: str
    dbName: str
    dbUser: str
    dbPassword: str
    charset: Optional[str] = "utf8mb4"


class DatabaseResponse(BaseModel):
    engine: Literal["mysql", "postgresql"]
    dbName: str
    dbUser: str
    grantsScope: Literal["database-only"] = "database-only"
    status: Literal["created", "already_exists"]


class WebrootCreateRequest(BaseModel):
    requestId: str
    eleveLogin: str
    projetSlug: str
    owner: str
    group: str


class WebrootResponse(BaseModel):
    path: str
    owner: str
    group: str
    mode: str
    status: Literal["created", "already_exists"]


class ErrorResponse(BaseModel):
    errorCode: str
    message: str
