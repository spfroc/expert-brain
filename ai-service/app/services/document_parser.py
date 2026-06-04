from io import BytesIO
from pathlib import Path
from zipfile import ZipFile
import html
import re
import xml.etree.ElementTree as ET

import httpx
from pypdf import PdfReader

from app.schemas.document_parse import ParseResult, TextChunk
from app.services.text_chunker import chunk_text, estimate_token_count, normalize_text


SUPPORTED_TEXT_SUFFIXES = {'.txt', '.md', '.markdown', '.csv', '.json', '.log'}
LEGAL_ARTICLE_PATTERN = re.compile(r'第[一二三四五六七八九十百千零〇0-9]+条')


def parse_plain_text(content: bytes, filename: str | None = None, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    text = decode_text(content)
    normalized = normalize_text(text)
    return build_parse_result(filename, normalized, 'plain_text', chunk_size, chunk_overlap)


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
    result = build_parse_result(filename, text, 'docx', chunk_size, chunk_overlap)
    result.metadata['paragraph_count'] = len(paragraphs)
    return result


def parse_pdf(content: bytes, filename: str | None = None, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    reader = PdfReader(BytesIO(content))
    pages: list[str] = []

    for page in reader.pages:
        page_text = page.extract_text() or ''
        page_text = normalize_pdf_page_text(page_text)
        if page_text:
            pages.append(page_text)

    text = normalize_text('\n\n'.join(pages))
    if not text:
        raise ValueError('No extractable text found in PDF. The PDF may be scanned images and OCR is not supported yet.')

    result = build_parse_result(filename, text, 'pdf', chunk_size, chunk_overlap)
    result.metadata['page_count'] = len(reader.pages)
    result.metadata['text_page_count'] = len(pages)
    return result


async def parse_url(url: str, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    async with httpx.AsyncClient(timeout=20.0, follow_redirects=True) as client:
        response = await client.get(url, headers={"User-Agent": "ExpertBrainBot/0.1"})
        response.raise_for_status()

    html_text = response.text
    title = extract_html_title(html_text) or url
    text = extract_readable_text_from_html(html_text)

    return build_parse_result(
        title,
        text,
        'url_html',
        chunk_size,
        chunk_overlap,
        extra_metadata={
            "url": url,
            "status_code": response.status_code,
            "content_type": response.headers.get('content-type'),
        },
    )


def parse_uploaded_file(content: bytes, filename: str | None, content_type: str | None, chunk_size: int = 1200, chunk_overlap: int = 150) -> ParseResult:
    suffix = Path(filename or '').suffix.lower()

    if suffix == '.pdf' or content_type == 'application/pdf':
        return parse_pdf(content, filename, chunk_size, chunk_overlap)

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


def build_parse_result(
    filename: str | None,
    text: str,
    parser: str,
    chunk_size: int = 1200,
    chunk_overlap: int = 150,
    extra_metadata: dict | None = None,
) -> ParseResult:
    if should_use_legal_article_parser(text):
        chunks = chunk_legal_articles(text)
        parser_name = f'{parser}+legal_article'
    else:
        chunks = chunk_text(text, chunk_size, chunk_overlap)
        parser_name = parser

    metadata = {"parser": parser_name, "filename": filename}
    if extra_metadata:
        metadata.update(extra_metadata)

    return ParseResult(
        title=filename,
        content=text,
        chunks=chunks,
        metadata=metadata,
    )


def should_use_legal_article_parser(text: str) -> bool:
    return len(LEGAL_ARTICLE_PATTERN.findall(text)) >= 3


def chunk_legal_articles(text: str) -> list[TextChunk]:
    matches = list(LEGAL_ARTICLE_PATTERN.finditer(text))
    if not matches:
        return chunk_text(text)

    chunks: list[TextChunk] = []
    prefix = text[:matches[0].start()].strip()

    for match_index, match in enumerate(matches):
        start = match.start()
        end = matches[match_index + 1].start() if match_index + 1 < len(matches) else len(text)
        article_title = match.group(0)
        article_content = text[start:end].strip()
        if not article_content:
            continue

        content = f"{prefix}\n\n{article_content}".strip() if prefix and match_index == 0 else article_content
        chunks.append(
            TextChunk(
                index=len(chunks),
                title=article_title,
                content=content,
                token_count=estimate_token_count(content),
                metadata={
                    "parser": "legal_article",
                    "article_no": article_title,
                    "char_start": start,
                    "char_end": end,
                },
            )
        )

    return chunks


def normalize_pdf_page_text(text: str) -> str:
    text = text.replace('\r\n', '\n').replace('\r', '\n')
    text = re.sub(r'[ \t]+', ' ', text)
    text = re.sub(r'\n{3,}', '\n\n', text)
    return text.strip()


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
