from fastapi import APIRouter, File, HTTPException, UploadFile

from app.schemas.document_parse import ParseResult, ParseTextRequest, ParseUrlRequest
from app.services.document_parser import parse_plain_text, parse_uploaded_file, parse_url

router = APIRouter(prefix="/documents", tags=["documents"])


@router.post("/parse-text", response_model=ParseResult)
def parse_text(payload: ParseTextRequest) -> ParseResult:
    return parse_plain_text(
        payload.content.encode("utf-8"),
        filename=payload.filename,
        chunk_size=payload.chunk_size,
        chunk_overlap=payload.chunk_overlap,
    )


@router.post("/parse-file", response_model=ParseResult)
async def parse_file(file: UploadFile = File(...), chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    content = await file.read()
    try:
        return parse_uploaded_file(
            content,
            filename=file.filename,
            content_type=file.content_type,
            chunk_size=chunk_size,
            chunk_overlap=chunk_overlap,
        )
    except Exception as exc:
        raise HTTPException(status_code=422, detail=f"Failed to parse file: {exc}") from exc


@router.post("/parse-url", response_model=ParseResult)
async def parse_url_endpoint(payload: ParseUrlRequest) -> ParseResult:
    try:
        return await parse_url(
            str(payload.url),
            chunk_size=payload.chunk_size,
            chunk_overlap=payload.chunk_overlap,
        )
    except Exception as exc:
        raise HTTPException(status_code=422, detail=f"Failed to parse URL: {exc}") from exc
