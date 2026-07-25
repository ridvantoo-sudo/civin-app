<?php

namespace App\Features\GameAnalytics\Services;

use App\Features\GameAnalytics\DTO\ExportRequestData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Native CSV / SpreadsheetML (.xls) / minimal PDF exporters (no third-party packages).
 */
final class ExportService
{
    public function __construct(
        private readonly AnalyticsQueryService $query,
    ) {}

    /**
     * @return array{path: string, disk: string, filename: string, mime: string}
     */
    public function export(ExportRequestData $request): array
    {
        $dataset = $this->resolveDataset($request);
        $rows = $this->flattenRows($dataset, $request->columns);
        $format = strtolower($request->format);
        $filename = sprintf(
            'analytics-%s-%s.%s',
            Str::slug($request->reportType),
            now()->format('YmdHis'),
            $format === 'excel' ? 'xls' : $format,
        );

        $contents = match ($format) {
            'csv' => $this->toCsv($rows),
            'xls', 'excel' => $this->toExcelXml($rows),
            'pdf' => $this->toPdf($rows, $request->reportType),
            default => throw new \InvalidArgumentException("Unsupported export format [{$request->format}]"),
        };

        $disk = (string) config('game_analytics.export.disk', 'local');
        $directory = trim((string) config('game_analytics.export.path', 'exports/game-analytics'), '/');
        $path = $directory.'/'.$filename;
        Storage::disk($disk)->put($path, $contents);

        return [
            'path' => $path,
            'disk' => $disk,
            'filename' => $filename,
            'mime' => match ($format) {
                'csv' => 'text/csv',
                'xls', 'excel' => 'application/vnd.ms-excel',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            },
            'contents' => $contents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDataset(ExportRequestData $request): array
    {
        return match ($request->reportType) {
            'revenue' => $this->query->revenue($request->filters),
            'games' => $this->query->games($request->filters),
            'players' => $this->query->players($request->filters),
            'retention' => $this->query->retention($request->filters),
            'live' => $this->query->live($request->filters),
            'agencies' => $this->query->agencies($request->filters),
            'vip' => $this->query->vip($request->filters),
            default => $this->query->overview($request->filters),
        };
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @param  list<string>  $columns
     * @return list<array<string, scalar|null>>
     */
    private function flattenRows(array $dataset, array $columns = []): array
    {
        $items = $dataset['items']
            ?? $dataset['series']
            ?? $dataset['rooms']
            ?? $dataset['top_hosts']
            ?? [];

        if ($items instanceof Collection) {
            $items = $items->all();
        }

        if (! is_array($items) || $items === []) {
            $kpis = $dataset['kpis'] ?? $dataset;
            $items = [is_array($kpis) ? $kpis : ['value' => $kpis]];
        }

        $max = (int) config('game_analytics.export.max_rows', 50000);
        $rows = [];
        foreach (array_slice($items, 0, $max) as $item) {
            $array = is_object($item) && method_exists($item, 'toArray')
                ? $item->toArray()
                : (array) $item;
            $flat = $this->flattenAssoc($array);
            if ($columns !== []) {
                $flat = array_intersect_key($flat, array_flip($columns));
            }
            $rows[] = $flat;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, scalar|null>
     */
    private function flattenAssoc(array $input, string $prefix = ''): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $out = array_merge($out, $this->flattenAssoc($value, $path));
            } elseif (is_scalar($value) || $value === null) {
                $out[$path] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        $headers = array_keys($rows[0] ?? ['empty' => '']);
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * Excel-readable SpreadsheetML XML (.xls).
     *
     * @param  list<array<string, scalar|null>>  $rows
     */
    private function toExcelXml(array $rows): string
    {
        $headers = array_keys($rows[0] ?? ['empty' => '']);
        $xml = [];
        $xml[] = '<?xml version="1.0"?>';
        $xml[] = '<?mso-application progid="Excel.Sheet"?>';
        $xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml[] = ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml[] = '<Worksheet ss:Name="Analytics"><Table>';

        $xml[] = '<Row>';
        foreach ($headers as $header) {
            $xml[] = '<Cell><Data ss:Type="String">'.$this->xml($header).'</Data></Cell>';
        }
        $xml[] = '</Row>';

        foreach ($rows as $row) {
            $xml[] = '<Row>';
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $type = is_numeric($value) ? 'Number' : 'String';
                $xml[] = '<Cell><Data ss:Type="'.$type.'">'.$this->xml((string) $value).'</Data></Cell>';
            }
            $xml[] = '</Row>';
        }

        $xml[] = '</Table></Worksheet></Workbook>';

        return implode('', $xml);
    }

    /**
     * Minimal valid PDF with plain text table summary.
     *
     * @param  list<array<string, scalar|null>>  $rows
     */
    private function toPdf(array $rows, string $title): string
    {
        $lines = ['Civin Game Analytics', $title, 'Generated: '.now()->toDateTimeString(), ''];
        $headers = array_keys($rows[0] ?? []);
        if ($headers !== []) {
            $lines[] = implode(' | ', $headers);
            foreach (array_slice($rows, 0, 40) as $row) {
                $lines[] = implode(' | ', array_map(
                    fn ($h) => (string) ($row[$h] ?? ''),
                    $headers,
                ));
            }
        } else {
            $lines[] = 'No rows';
        }

        $text = implode("\n", $lines);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 10 Tf 40 750 Td 14 TL ({$escaped}) Tj ET";
        $len = strlen($stream);

        $objects = [];
        $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';
        $objects[] = '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj';
        $objects[] = '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj';
        $objects[] = "4 0 obj<< /Length {$len} >>stream\n{$stream}\nendstream endobj";
        $objects[] = '5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.count($offsets)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer<< /Size '.count($offsets).' /Root 1 0 R >>\n';
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
