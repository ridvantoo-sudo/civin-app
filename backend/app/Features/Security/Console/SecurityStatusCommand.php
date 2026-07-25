<?php

namespace App\Features\Security\Console;

use App\Features\Security\Services\SecurityQueryService;
use Illuminate\Console\Command;

final class SecurityStatusCommand extends Command
{
    protected $signature = 'security:status';

    protected $description = 'Print a snapshot of the anti-cheat platform state';

    public function handle(SecurityQueryService $query): int
    {
        $overview = $query->overview();

        $rows = [];
        foreach ($overview as $key => $value) {
            $rows[] = [$key, is_scalar($value) ? (string) $value : json_encode($value)];
        }

        $this->table(['Metric', 'Value'], $rows);
        $this->line(sprintf(
            'enabled=%s enforcement=%s queue=%s',
            config('security_platform.enabled') ? 'yes' : 'no',
            config('security_platform.enforcement_enabled') ? 'yes' : 'no',
            (string) config('security_platform.queue', 'security'),
        ));

        return self::SUCCESS;
    }
}
