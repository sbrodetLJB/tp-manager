import os
import tempfile

# Doit être fait avant tout import de tpagent.* : Settings() est instancié au
# chargement du module tpagent.config.
os.environ["TPAGENT_BEARER_TOKEN"] = "test-token"
os.environ["TPAGENT_DB_ENGINE"] = "mysql"
os.environ["TPAGENT_SCRIPTS_DIR"] = "/nonexistent-in-tests"
os.environ["TPAGENT_JOB_LEDGER_PATH"] = os.path.join(tempfile.mkdtemp(prefix="tpagent-tests-"), "job_ledger.sqlite")
