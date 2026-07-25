<?php

namespace App\Features\Security\Console;

use App\Features\Security\Jobs\PurgeSecurityDataJob;
use Illuminate\Console\Command;

final class PurgeSecurityDataCommand extends Command
{
    protected $signature = 'security:purge {--sync : Run inline instead of queueing}';

    protected $description = 'Apply the configured security data retention policy';

    public function handle(): int
    {
        if ($this->option('sync')) {
            dispatch_sync(new PurgeSecurityDataJob);
            $this->info('Security retention policy applied.');

            return self::SUCCESS;
        }

        dispatch(new PurgeSecurityDataJob);
        $this->info('Queued security data retention purge.');

        return self::SUCCESS;
    }
}
