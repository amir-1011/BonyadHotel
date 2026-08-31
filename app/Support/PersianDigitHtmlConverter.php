<?php

namespace App\Support;

use Zarbinco\PersianCore\Contracts\PersianNumberNormalizerContract;

class PersianDigitHtmlConverter
{
    /** @var list<string> */
    private const SKIP_BLOCKS = ['script', 'style', 'textarea', 'noscript'];

    public function __construct(
        private readonly PersianNumberNormalizerContract $numbers,
    ) {}

    public function toPersian(string|int|float|null $value): string
    {
        return $this->numbers->toPersian($value);
    }

    public function toEnglish(string|int|float|null $value): string
    {
        return $this->numbers->toEnglish($value);
    }

    public function convertHtml(string $html): string
    {
        if ($html === '' || (strpbrk($html, '0123456789') === false && @preg_match('/[٠-٩]/u', $html) !== 1)) {
            return $html;
        }

        $length = strlen($html);
        $pos = 0;
        $out = '';

        while ($pos < $length) {
            $lt = strpos($html, '<', $pos);
            if ($lt === false) {
                $out .= $this->toPersian(substr($html, $pos));
                break;
            }

            if ($lt > $pos) {
                $out .= $this->toPersian(substr($html, $pos, $lt - $pos));
            }

            if (substr($html, $lt, 4) === '<!--') {
                $end = strpos($html, '-->', $lt + 4);
                if ($end === false) {
                    $out .= substr($html, $lt);
                    break;
                }
                $out .= substr($html, $lt, $end + 3 - $lt);
                $pos = $end + 3;
                continue;
            }

            $skipTag = $this->skipBlockTagAt($html, $lt);
            if ($skipTag !== null) {
                $close = $this->findSkipBlockEnd($html, $skipTag['openEnd'], $skipTag['name']);
                if ($close === false) {
                    $out .= substr($html, $lt);
                    break;
                }
                $out .= substr($html, $lt, $close - $lt);
                $pos = $close;
                continue;
            }

            $tagEnd = $this->findTagEnd($html, $lt);
            $out .= substr($html, $lt, $tagEnd - $lt);
            $pos = $tagEnd;
        }

        return $out;
    }

    /**
     * @return array{name: string, openEnd: int}|null
     */
    private function skipBlockTagAt(string $html, int $lt): ?array
    {
        foreach (self::SKIP_BLOCKS as $name) {
            $open = '<'.$name;
            if (! str_starts_with(strtolower(substr($html, $lt, strlen($open) + 1)), $open)) {
                continue;
            }

            $after = $html[$lt + strlen($open)] ?? '';
            if ($after !== '' && $after !== '>' && $after !== '/' && ! ctype_space($after)) {
                continue;
            }

            $openEnd = $this->findTagEnd($html, $lt);

            return ['name' => $name, 'openEnd' => $openEnd];
        }

        return null;
    }

    private function findSkipBlockEnd(string $html, int $from, string $name): int|false
    {
        $needle = '</'.$name;
        $close = stripos($html, $needle, $from);
        if ($close === false) {
            return false;
        }

        $gt = strpos($html, '>', $close + strlen($needle));

        return $gt === false ? false : $gt + 1;
    }

    private function findTagEnd(string $html, int $lt): int
    {
        $length = strlen($html);
        $quote = null;

        for ($i = $lt + 1; $i < $length; $i++) {
            $ch = $html[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                continue;
            }
            if ($ch === '>') {
                return $i + 1;
            }
        }

        return $length;
    }

    public function convertLivewireJson(string $json): string
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['components']) || ! is_array($data['components'])) {
            return $json;
        }

        $changed = false;

        foreach ($data['components'] as $i => $component) {
            if (! is_array($component)) {
                continue;
            }

            $html = $component['effects']['html'] ?? null;
            if (! is_string($html) || $html === '') {
                continue;
            }

            $converted = $this->convertHtml($html);
            if ($converted === $html) {
                continue;
            }

            $data['components'][$i]['effects']['html'] = $converted;
            $changed = true;
        }

        if (! $changed) {
            return $json;
        }

        $encoded = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($encoded) ? $encoded : $json;
    }

    public function convertArray(mixed $value, array $skipKeys = ['snapshot', 'checksum']): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array($key, $skipKeys, true)) {
                    $out[$key] = $item;
                    continue;
                }
                $out[$key] = $this->convertArray($item, $skipKeys);
            }

            return $out;
        }

        if (is_string($value)) {
            return $this->toEnglish($value);
        }

        return $value;
    }
}
