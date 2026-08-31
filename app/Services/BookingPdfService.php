<?php

namespace App\Services;

use App\Models\Booking;
use App\Support\PdfPersian;
use App\Support\VeteranGroups;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class BookingPdfService
{
    public function render(Booking $booking): string
    {
        $booking->loadMissing([
            'user.country', 'user.residenceCity', 'accommodation.city.province', 'roomType', 'roomRate',
            'services', 'guestDetails.country', 'guestDetails.residenceCity',
            'guestDetails.bookingRoom.room', 'guestDetails.bookingRoom.roomType',
            'createdBy', 'beneficiaryCosts.beneficiary.user', 'beneficiaryCosts.user',
            'bookingRooms.roomType', 'bookingRooms.roomRate', 'bookingRooms.room',
            'employer', 'medicalTariff', 'medicalContract',
        ]);

        $html = app(\App\Support\PersianDigitHtmlConverter::class)->convertHtml(View::make('pdf.booking-receipt', [
            'booking'             => $booking,
            'pricing'             => app(BookingReceiptBreakdownService::class)->pricingForBooking($booking),
            'veteranLabel' => $booking->veteranDiscountLabel(),
            'paymentLabel' => $this->paymentLabel($booking->payment_method),
            'issuedAt'     => PdfPersian::jalali(now(), 'Y/m/d H:i'),
            'checkInJalali'  => PdfPersian::jalali($booking->check_in),
            'checkOutJalali' => PdfPersian::jalali($booking->check_out),
        ])->render());

        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function makeMpdf(): Mpdf
    {
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $customFontDir = resource_path('fonts/pdf');
        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return new Mpdf([
            'mode'                 => 'utf-8',
            'format'               => 'A4',
            'margin_left'          => 10,
            'margin_right'         => 10,
            'margin_top'           => 10,
            'margin_bottom'        => 10,
            'shrink_tables_to_fit' => 1,
            'directionality'       => 'rtl',
            'tempDir'              => $tempDir,
            'fontDir'              => array_merge($fontDirs, [$customFontDir]),
            'fontdata'             => $fontData + [
                'vazirmatn' => [
                    'R'          => 'Vazirmatn-Regular.ttf',
                    'B'          => 'Vazirmatn-Bold.ttf',
                    'useOTL'     => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font'         => 'vazirmatn',
            'autoScriptToLang'     => true,
            'autoLangToFont'       => true,
            'autoArabic'           => true,
            'auto_language_detection' => true,
        ]);
    }

    public function paymentLabel(?string $method): string
    {
        return match ($method) {
            'cash'          => 'نقدی',
            'card_terminal' => 'کارتخوان',
            'medical_accommodation' => 'اسکان درمانی',
            'credit' => 'اعتباری',
            default         => '—',
        };
    }
}
