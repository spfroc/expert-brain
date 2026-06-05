<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EmbeddingHealthCommand extends Command
{
    protected $signature = 'embedding:health
        {--warmup : Call warmup endpoint before testing embed}
        {--timeout=180 : HTTP timeout seconds}
        {--text=测试 embedding 是否正常 : Test text}';

    protected $description = 'Check ai-service embedding status, warmup, and embed latency from backend container.';

    public function handle(): int
    {
        $baseUrl = rtrim(config('services.ai_service.url', 'http://ai-service:8000'), '/');
        $timeout = max(5, (int) $this->option('timeout'));
        $text = (string) $this->option('text');

        $this->info('AI service: '.$baseUrl);

        $this->line('');
        $this->info('Status');
        $status = $this->request('GET', $baseUrl.'/embeddings/status', null, $timeout);
        $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ((bool) $this->option('warmup')) {
            $this->line('');
            $this->info('Warmup');
            $warmup = $this->request('POST', $baseUrl.'/embeddings/warmup', [], $timeout);
            $this->line(json_encode($warmup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->line('');
        $this->info('Embed test');
        $startedAt = microtime(true);
        $embed = $this->request('POST', $baseUrl.'/embeddings/embed', [
            'texts' => [$text],
            'normalize' => true,
        ], $timeout);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->table(
            ['Provider', 'Model', 'Dimension', 'Vector Count', 'Elapsed MS', 'Metadata'],
            [[
                $embed['provider'] ?? null,
                $embed['model'] ?? null,
                $embed['dimension'] ?? null,
                isset($embed['embeddings']) && is_array($embed['embeddings']) ? count($embed['embeddings']) : 0,
                $elapsedMs,
                json_encode($embed['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
            ]]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?array $payload, int $timeout): array
    {
        $startedAt = microtime(true);
        $client = Http::timeout($timeout);
        $response = $method === 'GET'
            ? $client->get($url)
            : $client->post($url, $payload ?? []);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            $this->error("HTTP {$response->status()} after {$elapsedMs}ms: ".mb_substr($response->body(), 0, 1000));
            throw new \RuntimeException('Embedding health check failed.');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('Non-JSON response from '.$url);
        }

        $json['_http_elapsed_ms'] = $elapsedMs;
        return $json;
    }
}
