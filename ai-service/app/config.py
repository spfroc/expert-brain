from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = "ExpertBrain AI Service"
    app_env: str = "local"
    log_level: str = "INFO"

    postgres_host: str = "localhost"
    postgres_port: int = 5432
    postgres_db: str = "expert_brain"
    postgres_user: str = "expert_brain"
    postgres_password: str = "expert_brain"

    redis_url: str = "redis://localhost:6379/0"

    embedding_provider: str = "mock"
    embedding_model: str = "mock-embedding-1024"
    embedding_model_path: str | None = None
    embedding_dimension: int = 1024
    embedding_device: str = "cpu"

    llm_provider: str = "mock"
    llm_base_url: str | None = None
    llm_model: str | None = None

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")


settings = Settings()
