<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PcPosAgentClient
{
    /**
     * @param  list<array{amount: string, iban: string, trackingId: string}>  $shares
     */
    public function purchase(
        string $windowsLanIp,
        string $posLanIp,
        int $amount,
        int $posPort,
        int $agentPort,
        array $shares = [],
    ): PcPosPurchaseResult {
        $payload = [
            'ConnectionType' => 'TCP',
            'POSIP' => $posLanIp,
            'POSPORT' => (string) $posPort,
            'PR' => (string) config('pcpos.purchase_code', '000000'),
            'CU' => (string) config('pcpos.currency', '364'),
            'AM' => (string) $amount,
        ];

        if ($shares !== []) {
            $payload['Shares'] = $shares;
        }

        $url = $this->endpointUrl($windowsLanIp, $agentPort);
        $timeout = max(30, (int) config('pcpos.timeout_seconds', 180));
        $connectTimeout = max(3, (int) config('pcpos.connect_timeout_seconds', 15));

        set_time_limit($timeout + 30);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->asJson()
                ->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('pcpos.agent_connection_failed', [
                'url' => $url,
                'pos_ip' => $posLanIp,
                'amount' => $amount,
                'error' => $exception->getMessage(),
            ]);

            return PcPosPurchaseResult::connectionFailure(
                'اتصال به سیستم ویندوز اقامتگاه برقرار نشد. شبکه Tailscale و اجرای Agent را بررسی کنید.',
                $payload,
            );
        } catch (\Throwable $exception) {
            Log::warning('pcpos.agent_request_failed', [
                'url' => $url,
                'pos_ip' => $posLanIp,
                'amount' => $amount,
                'error' => $exception->getMessage(),
            ]);

            return PcPosPurchaseResult::connectionFailure(
                'ارسال درخواست به دستگاه پوز با خطا مواجه شد: ' . $exception->getMessage(),
                $payload,
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        $result = PcPosPurchaseResult::fromAgentResponse($response->status(), $json, $payload);

        Log::info('pcpos.agent_response', [
            'url' => $url,
            'pos_ip' => $posLanIp,
            'amount' => $amount,
            'http_status' => $response->status(),
            'resp_code' => $result->respCode,
            'resp_msg' => $result->respMsg,
            'rs' => $result->rs(),
            'approved' => $result->approved(),
        ]);

        return $result;
    }

    public function endpointUrl(string $windowsLanIp, int $agentPort): string
    {
        $host = filter_var($windowsLanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? '[' . $windowsLanIp . ']'
            : $windowsLanIp;

        $path = (string) config('pcpos.agent_path', '/pcpos');
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        return sprintf('http://%s:%d%s', $host, $agentPort, $path);
    }
}
