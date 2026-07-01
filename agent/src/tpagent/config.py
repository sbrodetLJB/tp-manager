from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="TPAGENT_")

    contract_version: str = "v1"
    db_engine: str = "mysql"
    web_root_base: str = "/var/www/html"
    bearer_token: str = ""
    scripts_dir: str = "/opt/tpagent/scripts"
    job_ledger_path: str = "/opt/tpagent/job_ledger.sqlite"
    sftp_chroot_strategy: str = "openssh-internal-sftp"


settings = Settings()
