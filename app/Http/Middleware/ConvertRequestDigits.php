<?php

namespace App\Http\Middleware;

use App\Support\PersianDigitHtmlConverter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;

class ConvertRequestDigits
{
    public function __construct(
        private readonly PersianDigitHtmlConverter $digits,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->replaceBag($request->query);
        $this->replaceBag($request->request);

        if ($this->requestHasJsonBody($request)) {
            $json = $request->json();
            if ($json instanceof ParameterBag) {
                $this->replaceBag($json);
            }
        }

        return $next($request);
    }

    private function requestHasJsonBody(Request $request): bool
    {
        if ($request->isJson()) {
            return true;
        }

        $contentType = (string) $request->header('Content-Type', '');

        return str_contains($contentType, 'json');
    }

    private function replaceBag(ParameterBag $bag): void
    {
        $all = $bag->all();
        if ($all === []) {
            return;
        }

        $bag->replace($this->digits->convertArray($all));
    }
}
