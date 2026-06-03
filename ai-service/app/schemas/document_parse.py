from pydantic import BaseModel, Field, HttpUrl


class TextChunk(BaseModel):
    index: int
    title: str | None = None
    content: str
    token_count: int | None = None
    metadata: dict = Field(default_factory=dict)


class ParseTextRequest(BaseModel):
    filename: str | None = None
    content: str
    chunk_size: int = 1200
    chunk_overlap: int = 150


class ParseUrlRequest(BaseModel):
    url: HttpUrl
    chunk_size: int = 1200
    chunk_overlap: int = 150


class ParseResult(BaseModel):
    title: str | None = None
    content: str
    chunks: list[TextChunk]
    metadata: dict = Field(default_factory=dict)
