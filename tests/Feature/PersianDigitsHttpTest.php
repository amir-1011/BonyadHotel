<?php

namespace Tests\Feature;

use App\Support\JalaliDateTimeInput;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PersianDigitsHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function () {
            Route::get('/_pd-html', function () {
                return response(
                    '<html><body>'
                    .'<p>قیمت 1234 ریال</p>'
                    .'<script>var n=56;</script>'
                    .'<textarea>78</textarea>'
                    .'<button @click="guests>1">9 نفر</button>'
                    .'<input value="1404/01/02">'
                    .'</body></html>',
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8'],
                );
            });

            Route::post('/_pd-echo', function () {
                return response()->json(request()->all());
            });

            Route::get('/_pd-query', function () {
                return response()->json(['q' => request('q')]);
            });
        });

        Route::middleware('api')->post('/_pd-api-echo', function () {
            return response()->json([
                'mobile' => request('mobile'),
                'total' => 12000,
            ]);
        });
    }

    public function test_livewire_navigate_html_does_not_convert_digits_on_the_server(): void
    {
        $html = $this->withHeader('X-Livewire-Navigate', '1')
            ->get('/_pd-html')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('قیمت 1234 ریال', $html);
        $this->assertStringContainsString('<script>var n=56;</script>', $html);
        $this->assertStringNotContainsString('قیمت ۱۲۳۴', $html);
    }

    public function test_html_response_uses_persian_digits_in_text_nodes(): void
    {
        $html = $this->get('/_pd-html')->assertOk()->getContent();

        $this->assertStringContainsString('قیمت ۱۲۳۴ ریال', $html);
        $this->assertStringContainsString('۹ نفر', $html);
        $this->assertStringContainsString('<script>var n=56;</script>', $html);
        $this->assertStringContainsString('<textarea>78</textarea>', $html);
        $this->assertStringContainsString('@click="guests>1"', $html);
        $this->assertStringContainsString('value="1404/01/02"', $html);
        $this->assertStringNotContainsString('قیمت 1234', $html);
    }

    public function test_form_post_normalizes_persian_and_arabic_digits_for_storage(): void
    {
        $this->post('/_pd-echo', [
            'mobile' => '۰۹۱۲۱۲۳۴۵۶۷',
            'national_id' => '٠٠١٢٣٣٤٤٥٥',
            'note' => 'اتاق 12',
        ])->assertOk()->assertJson([
            'mobile' => '09121234567',
            'national_id' => '0012334455',
            'note' => 'اتاق 12',
        ]);
    }

    public function test_query_string_persian_digits_are_normalized(): void
    {
        $this->get('/_pd-query?q=۱۴۰۴/۰۵/۰۱')
            ->assertOk()
            ->assertJson(['q' => '1404/05/01']);
    }

    public function test_json_post_normalizes_nested_livewire_updates_but_not_snapshot(): void
    {
        $this->postJson('/_pd-echo', [
            'components' => [[
                'snapshot' => '{"price":12000}',
                'updates' => ['mobile' => '۰۹۱۲۰۰۰۰۰۰۰'],
            ]],
        ])->assertOk()->assertJson([
            'components' => [[
                'snapshot' => '{"price":12000}',
                'updates' => ['mobile' => '09120000000'],
            ]],
        ]);
    }

    public function test_api_json_output_keeps_english_digits(): void
    {
        $this->postJson('/_pd-api-echo', [
            'mobile' => '۰۹۱۲۱۱۱۱۱۱۱',
        ])->assertOk()->assertExactJson([
            'mobile' => '09121111111',
            'total' => 12000,
        ]);
    }

    public function test_money_helper_accepts_all_digit_systems(): void
    {
        $this->assertSame(1250000, parse_money_input('۱,۲۵۰,۰۰۰'));
        $this->assertSame(1250000, parse_money_input('1,250,000'));
        $this->assertSame(1250000, parse_money_input('١٬٢٥٠٬٠٠٠'));
    }

    public function test_jalali_parser_accepts_persian_and_arabic_digits(): void
    {
        $en = JalaliDateTimeInput::normalizeDate('1404/05/01');
        $this->assertSame($en, JalaliDateTimeInput::normalizeDate('۱۴۰۴/۰۵/۰۱'));
        $this->assertSame($en, JalaliDateTimeInput::normalizeDate('١٤٠٤-٠٥-٠١'));
    }

    public function test_jalali_time_accepts_persian_digits(): void
    {
        $carbon = JalaliDateTimeInput::toCarbon('۱۴۰۴/۰۱/۰۱', '۱۴:۳۰');
        $this->assertSame(14, $carbon->hour);
        $this->assertSame(30, $carbon->minute);
    }
}
