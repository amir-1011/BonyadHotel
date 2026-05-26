<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersianDigits
{
    private const MAP = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                         '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        // Match (and preserve) <script>/<style> blocks and HTML tags,
        // then replace bare digit runs in text nodes only.
        // The tag pattern uses (?:[^>"']|"[^"]*"|'[^']*') to correctly skip over
        // quoted attribute values — preventing false-positive matches when attribute
        // values contain ">" (e.g. Alpine @click="guests>1 && guests--").
        $content = preg_replace_callback(
            '/(<(?:script|style)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>[\s\S]*?<\/(?:script|style)>)|(<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)|([0-9]+)/i',
            static function (array $m): string {
                // Groups 1 or 2: inside a tag or a script/style block — leave untouched
                if ($m[1] !== '' || $m[2] !== '') {
                    return $m[0];
                }
                // Group 3: bare digits in text node — convert
                return strtr($m[3], self::MAP);
            },
            $content
        );

        $response->setContent($content);

        return $response;
    }
}
