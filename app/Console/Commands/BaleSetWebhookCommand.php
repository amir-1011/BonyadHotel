<?php

namespace App\Console\Commands;

use App\Services\BaleBotService;
use Illuminate\Console\Command;

class BaleSetWebhookCommand extends Command
{
    protected $signature = 'bale:set-webhook {url? : HTTPS webhook url}';
    protected $description = 'Register the Bale bot webhook.';

    public function handle(BaleBotService $baleBot): int
    {
        $secret = config('services.bale.webhook_secret');

        if (!$secret) {
            $this->error('BALE_WEBHOOK_SECRET is not configured.');
            return self::FAILURE;
        }

        $url = $this->argument('url') ?: config('services.bale.webhook_url') ?: route('bale.webhook', ['secret' => $secret], absolute: true);

        if (!$url) {
            $this->error('Webhook URL is missing. Pass it as an argument or set BALE_WEBHOOK_URL.');
            return self::FAILURE;
        }

        if (!str_starts_with($url, 'https://')) {
            $this->error('Webhook URL must use HTTPS.');
            return self::FAILURE;
        }

        $result = $baleBot->setWebhook($url, $secret);

        $this->info(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}