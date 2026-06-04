<?php

namespace App\Services\DocumentIngestion;

use App\Models\DocumentChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ManualDocumentChunkService
{
    /**
     * @return array<string, mixed>
     */
    public function chunkDocument(KnowledgeDocument $document): array
    {
        $content = trim((string) $document->content);

        if ($content === '') {
            return [
                'chunk_count' => 0,
                'message' => 'Document content is empty.',
            ];
        }

        if ($this->shouldUseLegalArticleParser($document, $content)) {
            $payload = $this->parseLegalArticles($document, $content);
        } else {
            $payload = $this->parsePlainText($document, $content);
        }

        $chunks = $payload['chunks'] ?? [];

        DocumentChunk::query()
            ->where('knowledge_document_id', $document->id)
            ->delete();

        foreach ($chunks as $chunk) {
            DocumentChunk::query()->create([
                'knowledge_document_id' => $document->id,
                'document_file_id' => null,
                'chunk_index' => $chunk['index'],
                'chunk_type' => $chunk['chunk_type'] ?? 'text',
                'title' => $chunk['title'] ?? null,
                'content' => $chunk['content'],
                'token_count' => $chunk['token_count'] ?? null,
                'metadata' => $chunk['metadata'] ?? [],
            ]);
        }

        $document->forceFill([
            'metadata' => array_merge($document->metadata ?? [], [
                'manual_chunked_at' => now()->toISOString(),
                'manual_chunk_count' => count($chunks),
                'parse_metadata' => $payload['metadata'] ?? [],
            ]),
        ])->save();

        return [
            'chunk_count' => count($chunks),
            'parser' => $payload['metadata']['parser'] ?? 'plain_text',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePlainText(KnowledgeDocument $document, string $content): array
    {
        $response = Http::timeout(60)->post($this->aiServiceUrl('/documents/parse-text'), [
            'filename' => $document->title,
            'content' => $content,
            'chunk_size' => 1200,
            'chunk_overlap' => 150,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service parse-text failed: '.$response->body());
        }

        return $response->json();
    }

    private function shouldUseLegalArticleParser(KnowledgeDocument $document, string $content): bool
    {
        if (in_array($document->source_type, ['policy', 'law', 'regulation'], true)) {
            return true;
        }

        preg_match_all('/第[一二三四五六七八九十百千零〇0-9]+条/u', $content, $matches);

        return count($matches[0] ?? []) >= 3;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseLegalArticles(KnowledgeDocument $document, string $content): array
    {
        $normalized = preg_replace("/\r\n|\r/u", "\n", $content) ?? $content;
        preg_match_all('/第[一二三四五六七八九十百千零〇0-9]+条/u', $normalized, $matches, PREG_OFFSET_CAPTURE);

        if (count($matches[0] ?? []) === 0) {
            return $this->fallbackLegalChunk($document, $normalized);
        }

        $chunks = [];
        $prefix = trim(mb_substr($normalized, 0, $matches[0][0][1]));

        foreach ($matches[0] as $index => $match) {
            $articleTitle = $match[0];
            $start = $match[1];
            $end = isset($matches[0][$index + 1]) ? $matches[0][$index + 1][1] : mb_strlen($normalized);
            $articleContent = trim(mb_substr($normalized, $start, $end - $start));

            if ($articleContent === '') {
                continue;
            }

            $chunkContent = $prefix !== '' && $index === 0
                ? trim($prefix."\n\n".$articleContent)
                : $articleContent;

            $chunks[] = [
                'index' => count($chunks),
                'chunk_type' => 'legal_article',
                'title' => $articleTitle,
                'content' => $chunkContent,
                'token_count' => mb_strlen($chunkContent),
                'metadata' => [
                    'parser' => 'legal_article',
                    'article_no' => $articleTitle,
                    'char_start' => $start,
                    'char_end' => $end,
                    'document_title' => $document->title,
                ],
            ];
        }

        return [
            'title' => $document->title,
            'content' => $normalized,
            'chunks' => $chunks,
            'metadata' => [
                'parser' => 'legal_article',
                'article_count' => count($chunks),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackLegalChunk(KnowledgeDocument $document, string $content): array
    {
        return [
            'title' => $document->title,
            'content' => $content,
            'chunks' => [[
                'index' => 0,
                'chunk_type' => 'text',
                'title' => null,
                'content' => $content,
                'token_count' => mb_strlen($content),
                'metadata' => [
                    'parser' => 'legal_article_fallback',
                    'char_start' => 0,
                    'char_end' => mb_strlen($content),
                ],
            ]],
            'metadata' => [
                'parser' => 'legal_article_fallback',
                'article_count' => 0,
            ],
        ];
    }

    private function aiServiceUrl(string $path): string
    {
        return rtrim(config('services.ai_service.url', 'http://ai-service:8000'), '/').$path;
    }
}
