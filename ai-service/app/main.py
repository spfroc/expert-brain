from fastapi import FastAPI

from app.config import settings
from app.routers import document_parse, health

app = FastAPI(title=settings.app_name)

app.include_router(health.router)
app.include_router(document_parse.router)


@app.get("/")
def root() -> dict[str, str]:
    return {"service": settings.app_name, "status": "ok"}
