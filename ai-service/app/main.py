import logging

from fastapi import FastAPI

from app.config import settings
from app.routers import document_parse, embedding, health
from app.services.embedding_service import embedding_service

logger = logging.getLogger(__name__)

app = FastAPI(title=settings.app_name)

app.include_router(health.router)
app.include_router(document_parse.router)
app.include_router(embedding.router)


@app.on_event('startup')
def preload_embedding_model() -> None:
    if not settings.embedding_preload:
        return

    try:
        result = embedding_service.warmup()
        logger.info('Embedding model preloaded: %s', result)
    except Exception:
        logger.exception('Embedding model preload failed')


@app.get('/')
def root() -> dict[str, str]:
    return {'service': settings.app_name, 'status': 'ok'}
