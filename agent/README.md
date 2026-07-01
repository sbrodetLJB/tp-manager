# tpagent

Agent privilégié installé sur la VM de TP. Expose une API REST (voir
[../contracts/openapi.yaml](../contracts/openapi.yaml)) consommée uniquement
par le dashboard.

## Développement

```bash
python -m venv .venv
./.venv/Scripts/pip install -e ".[dev]"   # ou .venv/bin/pip sur Linux/macOS
./.venv/Scripts/python -m uvicorn tpagent.main:app --app-dir src --reload
```

Ne jamais exécuter contre une vraie VM de TP pendant le développement — voir
`../tools/fake-vm/`.
