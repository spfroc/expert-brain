from fastapi import FastAPI

from app.config import settings
from app.routers import health

app = FastAPI(title=settings.app_name)

app.include_router(health.router)


@app.get("/")
def root() -> dict[str, str]:
    return {"service": settings.app_name, "status": "ok"}
