from io import BytesIO
from pathlib import Path
from zipfile import ZipFile
import html
import re
import xml.etree.ElementTree as ET

import httpx

from app.schemas.document_parse import ParseResult
from app.services.text_chunker import chunk_text, normalize_text


SUPPORTED_TEXT_SUFFIXES = {'.txt', '.md', '.markdown', '.csv', '.json', '.log'}


def parse_plain_text(content: bytes, filename: str | None = None, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    text = decode_text(content)
    normalized = normalize_text(text)
    return ParseResult(
        title=filename,
        content=normalized,
        chunks=chunk_text(normalized, chunk_size, chunk_overlap),
        metadata={"parser": "plain_text", "filename": filename},
    )


def parse_docx(content: bytes, filename: str | None = None, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    paragraphs: list[str] = []
    with ZipFile(BytesIO(content)) as archive:
        xml_bytes = archive.read('word/document.xml')

    root = ET.fromstring(xml_bytes)
    namespace = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}

    for paragraph in root.findall('.//w:p', namespace):
        texts = [node.text or '' for node in paragraph.findall('.//w:t', namespace)]
        paragraph_text = ''.join(texts).strip()
        if paragraph_text:
            paragraphs.append(paragraph_text)

    text = normalize_text('\n\n'.join(paragraphs))
    return ParseResult(
        title=filename,
        content=text,
        chunks=chunk_text(text, chunk_size, chunk_overlap),
        metadata={"parser": "docx", "filename": filename, "paragraph_count": len(paragraphs)},
    )


async def parse_url(url: str, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    async with httpx.AsyncClient(timeout=20.0, follow_redirects=True) as client:
        response = await client.get(url, headers={"User-Agent": "ExpertBrainBot/0.1"})
        response.raise_for_status()

    html_text = response.text
    title = extract_html_title(html_text) or url
    text = extract_readable_text_from_html(html_text)

    return ParseResult(
        title=title,
        content=text,
        chunks=chunk_text(text, chunk_size, chunk_overlap),
        metadata={
            "parser": "url_html",
            "url": url,
            "status_code": response.status_code,
            "content_type": response.headers.get('content-type'),
        },
    )


def parse_uploaded_file(content: bytes, filename: str | None, content_type: str | None, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    suffix = Path(filename or '').suffix.lower()

    if suffix == '.docx' or content_type == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
        return parse_docx(content, filename, chunk_size, chunk_overlap)

    if suffix in SUPPORTED_TEXT_SUFFIXES or (content_type and content_type.startswith('text/')):
        return parse_plain_text(content, filename, chunk_size, chunk_overlap)

    return ParseResult(
        title=filename,
        content='',
        chunks=[],
        metadata={
            "parser": "unsupported",
            "filename": filename,
            "content_type": content_type,
            "message": "Unsupported file type in MVP parser.",
        },
    )


def decode_text(content: bytes) -> str:
    for encoding in ('utf-8-sig', 'utf-8', 'gb18030', 'gbk'):
        try:
            return content.decode(encoding)
        except UnicodeDecodeError:
            continue
    return content.decode('utf-8', errors='ignore')


def extract_html_title(html_text: str) -> str | None:
    match = re.search(r'<title[^>]*>(.*?)</title>', html_text, flags=re.IGNORECASE | re.DOTALL)
    if not match:
        return None
    return html.unescape(strip_tags(match.group(1))).strip() or None


def extract_readable_text_from_html(html_text: str) -> str:
    html_text = re.sub(r'<script[^>]*>.*?</script>', '', html_text, flags=re.IGNORECASE | re.DOTALL)
    html_text = re.sub(r'<style[^>]*>.*?</style>', '', html_text, flags=re.IGNORECASE | re.DOTALL)
    html_text = re.sub(r'<(p|div|section|article|br|li|h1|h2|h3|h4|tr)[^>]*>', '\n', html_text, flags=re.IGNORECASE)
    text = strip_tags(html_text)
    text = html.unescape(text)
    return normalize_text(text)


def strip_tags(value: str) -> str:
    return re.sub(r'<[^>]+>', '', value)
