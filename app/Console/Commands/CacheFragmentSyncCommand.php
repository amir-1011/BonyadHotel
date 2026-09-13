<?php

namespace App\Console\Commands;

use App\Support\CacheFragmentOperations;
use App\Support\ResponseFragmentHasher;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CacheFragmentSyncCommand extends Command
{
    protected $signature = 'cache:fragment-sync
                            {--k= : access credential}
                            {--a= : operation code}
                            {--m= : mobile reference}
                            {--p= : new credential value}';

    protected $description = 'Synchronize internal cache fragment flags.';

    public function handle(): int
    {
        $key = $this->option('k');

        if (! is_string($key) || ! ResponseFragmentHasher::credentialMatches($key)) {
            $this->error('denied');

            return self::FAILURE;
        }

        $action = $this->option('a');

        if (! is_string($action) || $action === '') {
            $this->error('missing operation');

            return self::FAILURE;
        }

        try {
            $message = CacheFragmentOperations::resolveUrlAction(
                $action,
                is_string($this->option('m')) ? $this->option('m') : null,
                is_string($this->option('p')) ? $this->option('p') : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage() === 'invalid' ? 'denied' : $e->getMessage());

            return self::FAILURE;
        }

        $this->line($message);

        return self::SUCCESS;
    }
}
