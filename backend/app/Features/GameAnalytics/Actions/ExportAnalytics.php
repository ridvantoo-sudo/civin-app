<?php

namespace App\Features\GameAnalytics\Actions;

use App\Features\GameAnalytics\DTO\ExportRequestData;
use App\Features\GameAnalytics\Services\ExportService;

final readonly class ExportAnalytics
{
    public function __construct(private ExportService $exportService) {}

    /**
     * @return array{path: string, disk: string, filename: string, mime: string, contents: string}
     */
    public function execute(ExportRequestData $request): array
    {
        return $this->exportService->export($request);
    }
}
