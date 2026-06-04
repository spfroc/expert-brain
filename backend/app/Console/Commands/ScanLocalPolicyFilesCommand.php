<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class ScanLocalPolicyFilesCommand extends Command
{
    protected $signature = 'knowledge:scan-local-policy-files
        {--path= : Directory containing policy files}
        {--pattern=*.pdf : File pattern}
        {--limit=0 : Max files to scan, 0 means all}
        {--offset=0 : Number of matched files to skip}
        {--report= : Optional CSV report path}';

    protected $description = 'Scan local policy files for duplicate titles, file size, and basic PDF quality indicators.';

    public function handle(): int
    {
        $path = $this->stringOption('path');
        if ($path === '' || ! is_dir($path)) {
            $this->error('Please provide a valid --path directory.');
            return self::FAILURE;
        }

        $pattern = $this->stringOption('pattern', '*.pdf');
        $limit = max(0, (int) $this->stringOption('limit', '0'));
        $offset = max(0, (int) $this->stringOption('offset', '0'));
        $reportPath = $this->stringOption('report');

        $finder = Finder::create()
            ->files()
            ->in($path)
            ->name($pattern)
            ->sortByName();

        $allFiles = iterator_to_array($finder, false);
        $files = $offset > 0 ? array_slice($allFiles, $offset) : $allFiles;
        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        if ($files === []) {
            $this->warn('No files found.');
            return self::SUCCESS;
        }

        $rows = [];
        $titleCounts = [];
        foreach ($files as $file) {
            $filename = $file->getFilename();
            $title = $this->titleFromFilename($filename);
            $titleCounts[$title] = ($titleCounts[$title] ?? 0) + 1;
        }

        foreach ($files as $index => $file) {
            $realPath = $file->getRealPath();
            $filename = $file->getFilename();
            $title = $this->titleFromFilename($filename);
            $size = $file->getSize();
            $header = $this->readBytes($realPath, 8);
            $tail = $this->readTail($realPath, 2048);
            $bodySample = $this->readBytes($realPath, 1024 * 1024);

            $looksPdf = str_starts_with($header, '%PDF');
            $hasEof = str_contains($tail, '%%EOF');
            $hasTextOperators = preg_match('/\b(Tj|TJ|BT|ET)\b/', $bodySample) === 1;
            $hasImages = preg_match('/\/Image\b|\/XObject\b/', $bodySample) === 1;
            $risk = $this->riskLevel($looksPdf, $hasEof, $hasTextOperators, $hasImages, $size);

            $rows[] = [
                'no' => $offset + $index + 1,
                'filename' => $filename,
                'title' => $title,
                'size_kb' => round($size / 1024, 1),
                'duplicate_title' => $titleCounts[$title] > 1 ? 'yes' : 'no',
                'looks_pdf' => $looksPdf ? 'yes' : 'no',
                'has_eof' => $hasEof ? 'yes' : 'no',
                'has_text_markers' => $hasTextOperators ? 'yes' : 'no',
                'has_image_markers' => $hasImages ? 'yes' : 'no',
                'parse_risk' => $risk,
                'path' => $realPath,
            ];
        }

        $summary = [
            'selected' => count($rows),
            'duplicates' => count(array_filter($rows, fn ($row) => $row['duplicate_title'] === 'yes')),
            'high_risk' => count(array_filter($rows, fn ($row) => $row['parse_risk'] === 'high')),
            'medium_risk' => count(array_filter($rows, fn ($row) => $row['parse_risk'] === 'medium')),
        ];

        $this->info(sprintf(
            'Matched=%d Selected=%d Duplicates=%d HighRisk=%d MediumRisk=%d',
            count($allFiles),
            $summary['selected'],
            $summary['duplicates'],
            $summary['high_risk'],
            $summary['medium_risk'],
        ));

        $displayRows = array_map(fn ($row) => [
            $row['no'],
            $row['filename'],
            $row['size_kb'],
            $row['duplicate_title'],
            $row['has_text_markers'],
            $row['has_image_markers'],
            $row['parse_risk'],
        ], array_slice($rows, 0, 30));
        $this->table(['#', 'Filename', 'KB', 'Duplicate', 'TextMarkers', 'ImageMarkers', 'Risk'], $displayRows);

        if ($reportPath !== '') {
            $this->writeCsv($reportPath, $rows);
            $this->info('Report written to: '.$reportPath);
        }

        return self::SUCCESS;
    }

    private function riskLevel(bool $looksPdf, bool $hasEof, bool $hasTextOperators, bool $hasImages, int $size): string
    {
        if (! $looksPdf || ! $hasEof || $size <= 0) {
            return 'high';
        }

        if (! $hasTextOperators && $hasImages) {
            return 'high';
        }

        if (! $hasTextOperators) {
            return 'medium';
        }

        return 'low';
    }

    private function readBytes(string $path, int $length): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $data = fread($handle, $length) ?: '';
        fclose($handle);
        return $data;
    }

    private function readTail(string $path, int $length): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $size = filesize($path) ?: 0;
        fseek($handle, max(0, $size - $length));
        $data = fread($handle, $length) ?: '';
        fclose($handle);
        return $data;
    }

    private function titleFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/^\d+[_-]+/u', '', $name) ?: $name;
        return trim($name);
    }

    private function stringOption(string $name, string $default = ''): string
    {
        $value = $this->option($name);
        if (is_array($value)) {
            $value = reset($value);
        }
        if ($value === null || $value === false || $value === '') {
            return $default;
        }
        return (string) $value;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to write report: '.$path);
        }

        fputcsv($handle, array_keys($rows[0] ?? []));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}
