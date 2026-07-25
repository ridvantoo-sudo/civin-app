<?php

namespace App\Features\Security\Console;

use App\Features\Security\Rules\RuleProvider;
use Illuminate\Console\Command;

/**
 * Hot reload: drops the compiled rule set so the next analysed event picks up
 * database changes without a deploy or a worker restart.
 */
final class ReloadSecurityRulesCommand extends Command
{
    protected $signature = 'security:reload-rules';

    protected $description = 'Flush the security rule cache so rule changes take effect immediately';

    public function handle(RuleProvider $rules): int
    {
        $rules->flush();

        $this->info(sprintf('Reloaded %d security rules.', count($rules->all())));

        return self::SUCCESS;
    }
}
