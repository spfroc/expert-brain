import hashlib
import math
from functools import cached_property

from app.config import settings


class EmbeddingService:
    def __init__(self) -> None:
        self.provider = settings.embedding_provider
        self.model = settings.embedding_model
        self.dimension = settings.embedding_dimension

    @cached_property
    def local_model(self):
        if self.provider != 'sentence-transformers':
            return None

        from sentence_transformers import SentenceTransformer

        model_name_or_path = settings.embedding_model_path or settings.embedding_model
        return SentenceTransformer(model_name_or_path, device=settings.embedding_device)

    def embed(self, texts: list[str], normalize: bool = True) -> list[list[float]]:
        if self.provider == 'sentence-transformers':
            return self._sentence_transformers_embed(texts, normalize=normalize)

        return [self._hash_embedding(text, normalize=normalize) for text in texts]

    def _sentence_transformers_embed(self, texts: list[str], normalize: bool = True) -> list[list[float]]:
        model = self.local_model
        vectors = model.encode(texts, normalize_embeddings=normalize, convert_to_numpy=True).tolist()
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
