import hashlib
import math

from app.config import settings


class EmbeddingService:
    def __init__(self) -> None:
        self.provider = settings.embedding_provider
        self.model = settings.embedding_model
        self.dimension = settings.embedding_dimension

    def embed(self, texts: list[str], normalize: bool = True) -> list[list[float]]:
        # MVP fallback. This keeps the whole pipeline runnable before bge-m3 is downloaded.
        # Replace this branch with a real local sentence-transformers backend once the model is mounted.
        return [self._hash_embedding(text, normalize=normalize) for text in texts]

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
        # Simple mixed Chinese/English tokenizer for deterministic fallback embeddings.
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
