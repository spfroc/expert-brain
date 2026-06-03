from app.schemas.document_parse import TextChunk


def normalize_text(text: str) -> str:
    lines = [line.strip() for line in text.replace('\r\n', '\n').replace('\r', '\n').split('\n')]
    normalized_lines: list[str] = []
    previous_blank = False

    for line in lines:
        if not line:
            if not previous_blank:
                normalized_lines.append('')
            previous_blank = True
            continue
        normalized_lines.append(line)
        previous_blank = False

    return '\n'.join(normalized_lines).strip()


def estimate_token_count(text: str) -> int:
    # Mixed Chinese/English approximation. This is good enough for MVP chunk sizing.
    return max(1, len(text) // 2)


def chunk_text(text: str, chunk_size: int = 1200, chunk_overlap: int = 150) -> list[TextChunk]:
    normalized = normalize_text(text)
    if not normalized:
        return []

    chunk_size = max(300, chunk_size)
    chunk_overlap = min(max(0, chunk_overlap), chunk_size // 2)

    chunks: list[TextChunk] = []
    start = 0
    index = 0

    while start < len(normalized):
        end = min(len(normalized), start + chunk_size)
        candidate = normalized[start:end]

        # Prefer breaking at paragraph or sentence boundaries when possible.
        if end < len(normalized):
            break_points = [candidate.rfind('\n\n'), candidate.rfind('。'), candidate.rfind('.'), candidate.rfind(';'), candidate.rfind('；')]
            best_break = max(break_points)
            if best_break > chunk_size * 0.55:
                end = start + best_break + 1
                candidate = normalized[start:end]

        content = candidate.strip()
        if content:
            chunks.append(
                TextChunk(
                    index=index,
                    content=content,
                    token_count=estimate_token_count(content),
                    metadata={"char_start": start, "char_end": end},
                )
            )
            index += 1

        if end >= len(normalized):
            break
        start = max(0, end - chunk_overlap)

    return chunks
