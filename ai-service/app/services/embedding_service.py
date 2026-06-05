import hashlib
import math
import time
import traceback
from functools import cached_property
from typing import Any

from app.config import settings


class EmbeddingService:
    def __init__(self) -> None:
        self.provider = settings.embedding_provider
        self.model = settings.embedding_model
        self.dimension = settings.embedding_dimension
        self.device = settings.embedding_device
        self.model_path = settings.embedding_model_path
        self.request_count = 0
        self.success_count = 0
        self.failure_count = 0
        self.loaded_at: float | None = None
        self.load_elapsed_ms: int | None = None
        self.last_embed_elapsed_ms: int | None = None
        self.last_error: str | None = None
        self.last_error_trace: str | None = None

    @cached_property
    def local_model(self):
        if self.provider != 'sentence-transformers':
            return None

        started_at = time.perf_counter()
        from sentence_transformers import SentenceTransformer

        model_name_or_path = settings.embedding_model_path or settings.embedding_model
        model = SentenceTransformer(model_name_or_path, device=settings.embedding_device)
        self.loaded_at = time.time()
        self.load_elapsed_ms = int((time.perf_counter() - started_at) * 1000)
        return model

    def embed(self, texts: list[str], normalize: bool = True) -> list[list[float]]:
        self.request_count += 1
        started_at = time.perf_counter()
        try:
            if self.provider == 'sentence-transformers':
                result = self._sentence_transformers_embed(texts, normalize=normalize)
            else:
                result = [self._hash_embedding(text, normalize=normalize) for text in texts]

            self.success_count += 1
            self.last_error = None
            self.last_error_trace = None
            return result
        except Exception as exc:
            self.failure_count += 1
            self.last_error = str(exc)
            self.last_error_trace = traceback.format_exc(limit=20)
            raise
        finally:
            self.last_embed_elapsed_ms = int((time.perf_counter() - started_at) * 1000)

    def warmup(self) -> dict[str, Any]:
        started_at = time.perf_counter()
        vectors = self.embed(['embedding warmup 测试'], normalize=True)
        return {
            'ok': True,
            'provider': self.provider,
            'model': self.model,
            'dimension': len(vectors[0]) if vectors else self.dimension,
            'elapsed_ms': int((time.perf_counter() - started_at) * 1000),
            'load_elapsed_ms': self.load_elapsed_ms,
            'device': self.device,
            'model_path': self.model_path,
        }

    def status(self) -> dict[str, Any]:
        loaded = self.provider != 'sentence-transformers' or self.loaded_at is not None
        return {
            'provider': self.provider,
            'model': self.model,
            'dimension': self.dimension,
            'device': self.device,
            'model_path': self.model_path,
            'loaded': loaded,
            'loaded_at': self.loaded_at,
            'load_elapsed_ms': self.load_elapsed_ms,
            'last_embed_elapsed_ms': self.last_embed_elapsed_ms,
            'request_count': self.request_count,
            'success_count': self.success_count,
            'failure_count': self.failure_count,
            'last_error': self.last_error,
            'last_error_trace': self.last_error_trace,
        }

    def _sentence_transformers_embed(self, texts: list[str], normalize: bool = True) -> list[list[float]]:
        model = self.local_model
        vectors = model.encode(
            texts,
            normalize_embeddings=normalize,
            convert_to_numpy=True,
            show_progress_bar=False,
        ).tolist()
        if vectors:
            self.dimension = len(vectors[0])
        return vectors

    def _hash_embedding(self, text: str, normalize: bool = True) -> list[float]:
        vector = [0.0] * self.dimension
        if not text:
            return vector

        tokens = self._tokenize(text)
        for token in tokens:
            digest = hashlib.sha256(token.encode('utf-8')).digest()
            index = int.from_bytes(digest[:4], 'big') % self.dimension
            sign = 1.0 if digest[4] % 2 == 0 else -1.0
            vector[index] += sign

        if normalize:
            norm = math.sqrt(sum(value * value for value in vector))
            if norm > 0:
                vector = [value / norm for value in vector]

        return vector

    def _tokenize(self, text: str) -> list[str]:
        tokens: list[str] = []
        buffer: list[str] = []

        for char in text.lower():
            if char.isascii() and char.isalnum():
                buffer.append(char)
                continue

            if buffer:
                tokens.append(''.join(buffer))
                buffer.clear()

            if char.strip():
                tokens.append(char)

        if buffer:
            tokens.append(''.join(buffer))

        return tokens


embedding_service = EmbeddingService()
