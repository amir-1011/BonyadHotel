<?php

namespace Tests\Unit;

use App\Support\PdfPersian;
use App\Support\PersianDigitHtmlConverter;
use Tests\TestCase;

class PersianDigitHtmlConverterTest extends TestCase
{
    private PersianDigitHtmlConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = app(PersianDigitHtmlConverter::class);
    }

    public function test_converts_english_and_arabic_digits_to_persian(): void
    {
        $this->assertSame('۱۲۳۴۵۶۷۸۹۰', $this->converter->toPersian('1234567890'));
        $this->assertSame('۱۲۳۴۵۶۷۸۹۰', $this->converter->toPersian('١٢٣٤٥٦٧٨٩٠'));
        $this->assertSame('۱۲۳۴۵۶۷۸۹۰', $this->converter->toPersian('۱۲۳۴۵۶۷۸۹۰'));
    }

    public function test_converts_persian_and_arabic_digits_to_english(): void
    {
        $this->assertSame('1234567890', $this->converter->toEnglish('۱۲۳۴۵۶۷۸۹۰'));
        $this->assertSame('1234567890', $this->converter->toEnglish('١٢٣٤٥٦٧٨٩٠'));
        $this->assertSame('1234567890', $this->converter->toEnglish('1234567890'));
        $this->assertSame('قیمت 12000 ریال', $this->converter->toEnglish('قیمت ۱۲۰۰۰ ریال'));
    }

    public function test_html_converts_text_nodes_only(): void
    {
        $html = '<p>قیمت 12000 ریال</p>';
        $this->assertSame('<p>قیمت ۱۲۰۰۰ ریال</p>', $this->converter->convertHtml($html));
    }

    public function test_html_preserves_script_style_and_textarea(): void
    {
        $html = '<script>var n=56;</script><style>.x{width:12px}</style><textarea>78</textarea><span>90</span>';
        $out = $this->converter->convertHtml($html);

        $this->assertStringContainsString('<script>var n=56;</script>', $out);
        $this->assertStringContainsString('<style>.x{width:12px}</style>', $out);
        $this->assertStringContainsString('<textarea>78</textarea>', $out);
        $this->assertStringContainsString('<span>۹۰</span>', $out);
    }

    public function test_html_preserves_digits_inside_large_inline_scripts(): void
    {
        $js = "window.bnbStayPicker = {\n    maxStayNights: 365,\n    padStart(2, '0')\n};\n";
        $js .= str_repeat("const n = 12345 + 67890;\n", 400);
        $html = '<p>قیمت 12000</p><script>' . $js . '</script><p>9 نفر</p>';

        $out = $this->converter->convertHtml($html);

        $this->assertStringContainsString('قیمت ۱۲۰۰۰', $out);
        $this->assertStringContainsString('۹ نفر', $out);
        $this->assertStringContainsString('maxStayNights: 365', $out);
        $this->assertStringContainsString("padStart(2, '0')", $out);
        $this->assertStringContainsString('const n = 12345 + 67890;', $out);
        $this->assertStringNotContainsString('maxStayNights: ۳۶۵', $out);
    }

    public function test_html_preserves_numeric_literals_used_on_wire_navigate(): void
    {
        $html = <<<'HTML'
<p>12 رزرو</p>
<script>
(function () {
    let _mapGeneration = ns.generation || 0;
    return rect.width > 0 || rect.height > 0;
})();
</script>
<script type="application/json" id="payload">{"geoMax":12,"n":0}</script>
HTML;

        $out = $this->converter->convertHtml($html);

        $this->assertStringContainsString('۱۲ رزرو', $out);
        $this->assertStringContainsString('let _mapGeneration = ns.generation || 0;', $out);
        $this->assertStringContainsString('return rect.width > 0 || rect.height > 0;', $out);
        $this->assertStringContainsString('{"geoMax":12,"n":0}', $out);
    }

    public function test_html_preserves_alpine_attributes_containing_greater_than(): void
    {
        $html = '<button @click="guests>1 && guests--">Count 10</button>';
        $out = $this->converter->convertHtml($html);

        $this->assertStringContainsString('@click="guests>1 && guests--"', $out);
        $this->assertStringContainsString('Count ۱۰', $out);
        $this->assertStringNotContainsString('Count 10', $out);
    }

    public function test_livewire_json_preserves_snapshot_slash_escaping(): void
    {
        $innerSnapshot = json_encode(['url' => 'http://example.test/path', 'n' => 12], JSON_THROW_ON_ERROR);
        $payload = json_encode([
            'components' => [[
                'snapshot' => $innerSnapshot,
                'effects' => ['html' => '<span>12</span>'],
            ]],
        ], JSON_THROW_ON_ERROR);

        $decoded = json_decode($this->converter->convertLivewireJson($payload), true);

        $this->assertSame($innerSnapshot, $decoded['components'][0]['snapshot']);
        $this->assertSame('<span>۱۲</span>', $decoded['components'][0]['effects']['html']);
    }

    public function test_html_preserves_input_values_in_attributes(): void
    {
        $html = '<input type="text" value="1404/05/01" name="check_in">تاریخ 1404';
        $out = $this->converter->convertHtml($html);

        $this->assertStringContainsString('value="1404/05/01"', $out);
        $this->assertStringContainsString('تاریخ ۱۴۰۴', $out);
    }

    public function test_html_preserves_livewire_snapshot_attribute(): void
    {
        $html = '<div wire:snapshot="{&quot;data&quot;:{&quot;n&quot;:12}}">Item 12</div>';
        $out = $this->converter->convertHtml($html);

        $this->assertStringContainsString('wire:snapshot="{&quot;data&quot;:{&quot;n&quot;:12}}"', $out);
        $this->assertStringContainsString('Item ۱۲', $out);
    }

    public function test_html_converts_formatted_money_and_decimals(): void
    {
        $html = '<div>1,250,000.50</div>';
        $this->assertSame('<div>۱,۲۵۰,۰۰۰.۵۰</div>', $this->converter->convertHtml($html));
    }

    public function test_livewire_json_converts_html_but_not_snapshot(): void
    {
        $payload = json_encode([
            'components' => [[
                'snapshot' => '{"data":{"price":12000},"checksum":"abc123"}',
                'effects' => [
                    'html' => '<div wire:id="x">مبلغ 12000</div>',
                    'returns' => [],
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE);

        $out = json_decode($this->converter->convertLivewireJson($payload), true);

        $this->assertSame('{"data":{"price":12000},"checksum":"abc123"}', $out['components'][0]['snapshot']);
        $this->assertSame('<div wire:id="x">مبلغ ۱۲۰۰۰</div>', $out['components'][0]['effects']['html']);
    }

    public function test_non_livewire_json_is_left_untouched(): void
    {
        $json = '{"total":12000,"mobile":"09121234567"}';
        $this->assertSame($json, $this->converter->convertLivewireJson($json));
    }

    public function test_request_array_conversion_skips_snapshot(): void
    {
        $converted = $this->converter->convertArray([
            'mobile' => '۰۹۱۲۱۲۳۴۵۶۷',
            'nested' => ['code' => '١٢٣٤'],
            'snapshot' => 'قیمت ۱۲۰۰۰',
            'checksum' => '۱۲۳abc',
        ]);

        $this->assertSame('09121234567', $converted['mobile']);
        $this->assertSame('1234', $converted['nested']['code']);
        $this->assertSame('قیمت ۱۲۰۰۰', $converted['snapshot']);
        $this->assertSame('۱۲۳abc', $converted['checksum']);
    }

    public function test_pdf_persian_helpers_use_package(): void
    {
        $this->assertSame('۱,۰۰۰ ریال', PdfPersian::amount(1000));
        $this->assertSame('1404/01/01', PdfPersian::toEnglishDigits('۱۴۰۴/۰۱/۰۱'));
    }
}
