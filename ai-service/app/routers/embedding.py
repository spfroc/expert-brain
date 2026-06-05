from fastapi import APIRouter

from app.schemas.embedding import EmbedRequest, EmbedResponse
from app.services.embedding_service import embedding_service

router = APIRouter(prefix='/embeddings', tags=['embeddings'])


@router.get('/status')
def status():
    return embedding_service.status()


@router.post('/warmup')
def warmup():
    return embedding_service.warmup()


@router.post('/embed', response_model=EmbedResponse)
def embed(payload: EmbedRequest) -> EmbedResponse:
    embeddings = embedding_service.embed(payload.texts, normalize=payload.normalize)
    return EmbedResponse(
        provider=embedding_service.provider,
        model=embedding_service.model,
        dimension=embedding_service.dimension,
        embeddings=embeddings,
        metadata={
            'count': len(payload.texts),
            'last_embed_elapsed_ms': embedding_service.last_embed_elapsed_ms,
            'load_elapsed_ms': embedding_service.load_elapsed_ms,
            'loaded': embedding_service.status().get('loaded'),
        },
    )
