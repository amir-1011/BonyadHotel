<?php

namespace App\Http\Middleware;

use App\Support\PersianDigitHtmlConverter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersianDigits
{
    public function __construct(
        private readonly PersianDigitHtmlConverter $digits,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '') ?? '';
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return $response;
        }

        if (str_contains($contentType, 'text/html')) {
            // wire:navigate re-executes cloned <script> tags. Leave those
            // responses untouched so numeric literals stay valid JS; the
            // client observer converts visible digits after the swap.
            if ($request->headers->has('X-Livewire-Navigate')) {
                return $response;
            }

            $converted = $this->digits->convertHtml($content);
            if ($converted !== $content) {
                $response->setContent($converted);
            }

            return $response;
        }

        if (str_contains($contentType, 'json')) {
            $converted = $this->digits->convertLivewireJson($content);
            if ($converted !== $content) {
                $response->setContent($converted);
            }
        }

        return $response;
    }
}
