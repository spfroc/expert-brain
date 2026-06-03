from pydantic import BaseModel, Field


class EmbedRequest(BaseModel):
    texts: list[str] = Field(min_length=1)
    normalize: bool = True


class EmbedResponse(BaseModel):
    provider: str
    model: str
    dimension: int
    embeddings: list[list[float]]
    metadata: dict = Field(default_factory=dict)
