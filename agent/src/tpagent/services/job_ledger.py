import hashlib
import json
import sqlite3
from contextlib import closing
from pathlib import Path
from typing import Any, Dict, Optional

from tpagent.config import settings


class RequestIdReusedWithDifferentPayloadError(Exception):
    pass


class JobLedger:
    """Ledger d'idempotence local à l'agent (sqlite), séparé du stockage du
    dashboard. Un même requestId rejoué (ex: retry dashboard après timeout
    réseau) renvoie la réponse déjà enregistrée au lieu de ré-exécuter
    l'opération — voir docs/architecture.md section 3d.
    """

    def __init__(self, path: Optional[str] = None) -> None:
        self._path = path or settings.job_ledger_path
        Path(self._path).parent.mkdir(parents=True, exist_ok=True)
        with closing(self._connect()) as conn:
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS job_ledger (
                    request_id TEXT NOT NULL,
                    endpoint TEXT NOT NULL,
                    payload_hash TEXT NOT NULL,
                    status_code INTEGER NOT NULL,
                    response_body TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    PRIMARY KEY (request_id, endpoint)
                )
                """
            )
            conn.commit()

    def _connect(self) -> sqlite3.Connection:
        return sqlite3.connect(self._path)

    @staticmethod
    def _hash_payload(payload: Dict[str, Any]) -> str:
        return hashlib.sha256(json.dumps(payload, sort_keys=True).encode("utf-8")).hexdigest()

    def get_cached_response(self, request_id: str, endpoint: str, payload: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        with closing(self._connect()) as conn:
            row = conn.execute(
                "SELECT payload_hash, status_code, response_body FROM job_ledger WHERE request_id = ? AND endpoint = ?",
                (request_id, endpoint),
            ).fetchone()

        if row is None:
            return None

        stored_hash, status_code, response_body = row
        if stored_hash != self._hash_payload(payload):
            raise RequestIdReusedWithDifferentPayloadError(
                f'requestId "{request_id}" déjà utilisé pour "{endpoint}" avec un payload différent.'
            )

        return {"status_code": status_code, "body": json.loads(response_body)}

    def record(self, request_id: str, endpoint: str, payload: Dict[str, Any], status_code: int, body: Dict[str, Any]) -> None:
        with closing(self._connect()) as conn:
            conn.execute(
                "INSERT OR REPLACE INTO job_ledger (request_id, endpoint, payload_hash, status_code, response_body) "
                "VALUES (?, ?, ?, ?, ?)",
                (request_id, endpoint, self._hash_payload(payload), status_code, json.dumps(body)),
            )
            conn.commit()
