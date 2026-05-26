<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\Program;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $accommodations = Accommodation::with('roomTypes')->get();

        if ($accommodations->isEmpty()) {
            $this->command->warn('هیچ اقامتگاهی یافت نشد. ابتدا AccommodationSeeder را اجرا کنید.');
            return;
        }

        Program::query()->delete();

        // ─── تعاریف ثابت ────────────────────────────────────────────────────
        $supportiveServiceTypes = [
            'تخفیف جانبازان ۷۰ درصد و بالاتر',
            'تخفیف جانبازان ۵۰ تا ۶۹ درصد',
            'تخفیف جانبازان ۲۵ تا ۴۹ درصد',
            'تخفیف خانواده‌های شهدا',
            'تخفیف خانواده‌های آزادگان',
            'اردوی درمانی جانبازان شیمیایی',
            'اردوی توانبخشی جانبازان اعصاب و روان',
            'طرح مراقبت شهریاری بنیاد شهید',
        ];

        $employers = [
            'بنیاد شهید و امور ایثارگران',
            'ستاد کل نیروهای مسلح',
            'معاونت امور ایثارگران وزارت کشور',
            'سازمان بهزیستی کشور',
            'اداره کل بهزیستی استان تهران',
            'فرماندهی انتظامی جمهوری اسلامی ایران',
            'وزارت تعاون، کار و رفاه اجتماعی',
            'سپاه پاسداران انقلاب اسلامی',
            'ارتش جمهوری اسلامی',
            'هلال احمر جمهوری اسلامی ایران',
        ];

        $contractors = [
            'شرکت خدمات رفاهی سبز ایثار',
            'مؤسسه فرهنگی اردوهای جانبازان',
            'شرکت مسافرتی زیارت و سیاحت قدس',
            'مؤسسه رهپویان شهادت',
            'شرکت گردشگری نور ایرانیان',
            'تعاونی خدمات مسافرتی انصار',
            'شرکت فرهنگی هنری بنیادیار',
            'مؤسسه خدمات حمایتی ایثارگران',
            'شرکت گردشگری آفتاب سرخ',
            'مؤسسه خیریه نیک‌یار',
        ];

        // ─── برنامه‌های حمایتی (اردوهای بنیاد شهید) ────────────────────────
        $supportivePrograms = [
            [
                'title'                   => 'اردوی جانبازان شیمیایی منطقه ۱ تهران',
                'description'             => 'اردوی استراحت و درمان جانبازان شیمیایی با بیش از ۷۰ درصد جانبازی به همراه خانواده‌های محترمشان. این اردو با هدف ارائه خدمات درمانی و روان‌شناختی برگزار می‌گردد.',
                'program_type'            => 'camp',
                'start_date'              => '2026-06-01',
                'end_date'                => '2026-06-07',
                'rooms_allocated'         => 15,
                'guest_count'             => 48,
                'employer_key'            => 0,
                'contractor_key'          => 0,
                'total_amount'            => 240_000_000,
                'deposit_amount'          => 80_000_000,
                'discount_amount'         => 120_000_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'اردوی درمانی جانبازان شیمیایی',
                'notes'                   => 'نیاز به اتاق‌های دارای بالکن و هوای تازه؛ برخی جانبازان مشکل تنفسی دارند.',
                'status'                  => 'completed',
            ],
            [
                'title'                   => 'اردوی خانواده شهدای منطقه ۲ تهران',
                'description'             => 'اردوی تابستانی خانواده‌های معزز شهدا از مناطق ۲، ۵ و ۶ شهر تهران. برنامه شامل بازدید از جاذبه‌های گردشگری و مراسم فرهنگی است.',
                'program_type'            => 'camp',
                'start_date'              => '2026-07-10',
                'end_date'                => '2026-07-15',
                'rooms_allocated'         => 20,
                'guest_count'             => 75,
                'employer_key'            => 0,
                'contractor_key'          => 1,
                'total_amount'            => 375_000_000,
                'deposit_amount'          => 100_000_000,
                'discount_amount'         => 187_500_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف خانواده‌های شهدا',
                'notes'                   => 'تعداد ۱۲ نفر کودک زیر ۱۲ سال همراه خانواده‌ها هستند.',
                'status'                  => 'active',
            ],
            [
                'title'                   => 'اردوی توانبخشی جانبازان اعصاب و روان',
                'description'             => 'اردوی تخصصی توانبخشی برای جانبازان مبتلا به اختلالات پس از سانحه جنگ (PTSD) با همراهی تیم درمانی متخصص.',
                'program_type'            => 'camp',
                'start_date'              => '2026-08-05',
                'end_date'                => '2026-08-12',
                'rooms_allocated'         => 10,
                'guest_count'             => 28,
                'employer_key'            => 2,
                'contractor_key'          => 3,
                'total_amount'            => 196_000_000,
                'deposit_amount'          => 50_000_000,
                'discount_amount'         => 98_000_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'اردوی توانبخشی جانبازان اعصاب و روان',
                'notes'                   => 'نیاز به هماهنگی با تیم پزشکی اعزامی از بنیاد شهید جهت استقرار در محل.',
                'status'                  => 'active',
            ],
            [
                'title'                   => 'اردوی آزادگان و خانواده‌ها - بهار ۱۴۰۵',
                'description'             => 'اردوی بهاره خانواده‌های آزادگان گرامی به مناسبت سالگرد آزادی اسرا.',
                'program_type'            => 'camp',
                'start_date'              => '2026-04-15',
                'end_date'                => '2026-04-22',
                'rooms_allocated'         => 12,
                'guest_count'             => 42,
                'employer_key'            => 0,
                'contractor_key'          => 5,
                'total_amount'            => 252_000_000,
                'deposit_amount'          => 70_000_000,
                'discount_amount'         => 100_800_000,
                'discount_percentage'     => 40,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف خانواده‌های آزادگان',
                'notes'                   => 'برنامه مراسم یادمان در شب سوم اقامت پیش‌بینی شده است.',
                'status'                  => 'completed',
            ],
            [
                'title'                   => 'اردوی جانبازان ۵۰ تا ۷۰ درصد استان فارس',
                'description'             => 'اردوی استراحتی ویژه جانبازان ۵۰ تا ۷۰ درصد استان فارس همراه با برنامه‌های فرهنگی و ورزشی.',
                'program_type'            => 'camp',
                'start_date'              => '2026-09-01',
                'end_date'                => '2026-09-08',
                'rooms_allocated'         => 18,
                'guest_count'             => 60,
                'employer_key'            => 2,
                'contractor_key'          => 4,
                'total_amount'            => 360_000_000,
                'deposit_amount'          => 120_000_000,
                'discount_amount'         => 180_000_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف جانبازان ۵۰ تا ۶۹ درصد',
                'notes'                   => 'نیاز به حداقل ۵ اتاق مجهز به تخت طبی و گریل بهداشتی.',
                'status'                  => 'active',
            ],
            [
                'title'                   => 'رویداد سالانه ایثار و شهادت',
                'description'             => 'همایش بزرگ سالانه خانواده‌های ایثارگر به مناسبت هفته دفاع مقدس با حضور مسئولین بنیاد شهید.',
                'program_type'            => 'event',
                'start_date'              => '2026-09-22',
                'end_date'                => '2026-09-24',
                'rooms_allocated'         => 30,
                'guest_count'             => 90,
                'employer_key'            => 0,
                'contractor_key'          => 6,
                'total_amount'            => 450_000_000,
                'deposit_amount'          => 150_000_000,
                'discount_amount'         => 225_000_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'طرح مراقبت شهریاری بنیاد شهید',
                'notes'                   => 'سالن اجتماعات باید ظرفیت ۲۰۰ نفر داشته باشد.',
                'status'                  => 'active',
            ],
            [
                'title'                   => 'اردوی شهدای دانش‌آموز',
                'description'             => 'اردوی دانش‌آموزان فرزند شهدا از مدارس استان خراسان رضوی. برنامه‌های فرهنگی، آموزشی و تفریحی.',
                'program_type'            => 'camp',
                'start_date'              => '2026-05-20',
                'end_date'                => '2026-05-25',
                'rooms_allocated'         => 8,
                'guest_count'             => 35,
                'employer_key'            => 7,
                'contractor_key'          => 7,
                'total_amount'            => 140_000_000,
                'deposit_amount'          => 42_000_000,
                'discount_amount'         => 70_000_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف خانواده‌های شهدا',
                'notes'                   => 'دانش‌آموزان زیر ۱۸ سال؛ نیاز به سرویس نگهبان شبانه.',
                'status'                  => 'completed',
            ],
            [
                'title'                   => 'اردوی خانواده شهدای استان اصفهان',
                'description'             => 'اردوی تفریحی و معنوی خانواده شهدای استان اصفهان در تعطیلات نوروزی.',
                'program_type'            => 'camp',
                'start_date'              => '2026-03-25',
                'end_date'                => '2026-04-01',
                'rooms_allocated'         => 22,
                'guest_count'             => 80,
                'employer_key'            => 0,
                'contractor_key'          => 2,
                'total_amount'            => 480_000_000,
                'deposit_amount'          => 120_000_000,
                'discount_amount'         => 240_000_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف خانواده‌های شهدا',
                'notes'                   => 'برنامه بازدید از یادمان شهدا در روز سوم گنجانده شده است.',
                'status'                  => 'completed',
            ],
            [
                'title'                   => 'طرح ویژه مراقبت از جانبازان ۷۰٪+ بستری',
                'description'             => 'اسکان موقت جانبازان ۷۰ درصد و بالاتر که تحت مراقبت‌های پزشکی ویژه قرار دارند. به همراه یک پرستار اختصاصی.',
                'program_type'            => 'other',
                'start_date'              => '2026-05-01',
                'end_date'                => '2026-05-31',
                'rooms_allocated'         => 5,
                'guest_count'             => 10,
                'employer_key'            => 0,
                'contractor_key'          => 7,
                'total_amount'            => 150_000_000,
                'deposit_amount'          => 60_000_000,
                'discount_amount'         => 105_000_000,
                'discount_percentage'     => 70,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف جانبازان ۷۰ درصد و بالاتر',
                'notes'                   => 'اتاق‌های انتخابی باید به ویلچر دسترسی داشته باشند.',
                'status'                  => 'active',
            ],
            [
                'title'                   => 'اردوی جانبازان ۲۵ تا ۴۹ درصد - پاییز ۱۴۰۵',
                'description'             => 'اردوی پاییزه برای جانبازان از ۲۵ تا ۴۹ درصد به همراه همسر و فرزندان.',
                'program_type'            => 'camp',
                'start_date'              => '2026-10-10',
                'end_date'                => '2026-10-17',
                'rooms_allocated'         => 14,
                'guest_count'             => 50,
                'employer_key'            => 1,
                'contractor_key'          => 8,
                'total_amount'            => 300_000_000,
                'deposit_amount'          => 90_000_000,
                'discount_amount'         => 75_000_000,
                'discount_percentage'     => 25,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف جانبازان ۲۵ تا ۴۹ درصد',
                'notes'                   => 'درخواست برپایی نماز جماعت در محل اسکان.',
                'status'                  => 'active',
            ],
            [
                'title'                   => 'اردوی زمستانی آزادگان - دی ۱۴۰۵',
                'description'             => 'اردوی پذیرایی از آزادگان سرفراز در دوران آبوهوای زمستانی با برنامه‌های ویژه.',
                'program_type'            => 'camp',
                'start_date'              => '2027-01-05',
                'end_date'                => '2027-01-10',
                'rooms_allocated'         => 9,
                'guest_count'             => 30,
                'employer_key'            => 0,
                'contractor_key'          => 5,
                'total_amount'            => 180_000_000,
                'deposit_amount'          => 54_000_000,
                'discount_amount'         => 72_000_000,
                'discount_percentage'     => 40,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف خانواده‌های آزادگان',
                'notes'                   => 'آمادگی برای اسکان زمستانی و گرمایش مناسب ضروری است.',
                'status'                  => 'active',
            ],
            [
                'title'                   => 'اردوی حمایتی کنسل‌شده - بهمن ۱۴۰۴',
                'description'             => 'این اردو به دلیل تغییر در تأمین اعتبار لغو شد.',
                'program_type'            => 'camp',
                'start_date'              => '2026-01-20',
                'end_date'                => '2026-01-25',
                'rooms_allocated'         => 6,
                'guest_count'             => 18,
                'employer_key'            => 3,
                'contractor_key'          => 9,
                'total_amount'            => 90_000_000,
                'deposit_amount'          => 20_000_000,
                'discount_amount'         => 45_000_000,
                'discount_percentage'     => 50,
                'is_supportive_service'   => true,
                'supportive_service_type' => 'تخفیف خانواده‌های شهدا',
                'notes'                   => 'لغو به دلیل عدم تأمین بودجه.',
                'status'                  => 'cancelled',
            ],
        ];

        // ─── برنامه‌های معمولی (غیر حمایتی) ───────────────────────────────
        $regularPrograms = [
            [
                'title'               => 'همایش راهبردی مدیران منطقه',
                'description'         => 'همایش دو روزه مدیران منطقه‌ای شرکت‌های تابعه وزارت صمت. شامل کارگاه‌های آموزشی و میزگردهای تخصصی.',
                'program_type'        => 'event',
                'start_date'          => '2026-06-15',
                'end_date'            => '2026-06-17',
                'rooms_allocated'     => 25,
                'guest_count'         => 60,
                'employer_key'        => 6,
                'contractor_key'      => 4,
                'total_amount'        => 600_000_000,
                'deposit_amount'      => 200_000_000,
                'discount_amount'     => 30_000_000,
                'discount_percentage' => 5,
                'status'              => 'active',
            ],
            [
                'title'               => 'کنفرانس ملی نوآوری دیجیتال',
                'description'         => 'کنفرانس سه روزه با حضور متخصصان فناوری اطلاعات از سراسر کشور. سخنرانی‌های کلیدی، نمایشگاه استارتاپ‌ها.',
                'program_type'        => 'event',
                'start_date'          => '2026-07-20',
                'end_date'            => '2026-07-23',
                'rooms_allocated'     => 40,
                'guest_count'         => 120,
                'employer_key'        => 5,
                'contractor_key'      => 6,
                'total_amount'        => 1_200_000_000,
                'deposit_amount'      => 400_000_000,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'status'              => 'active',
            ],
            [
                'title'               => 'اردوی تفریحی کارکنان شرکت ملی نفت',
                'description'         => 'اردوی تابستانی خانوادگی کارکنان و بازنشستگان شرکت ملی نفت ایران.',
                'program_type'        => 'camp',
                'start_date'          => '2026-08-01',
                'end_date'            => '2026-08-08',
                'rooms_allocated'     => 35,
                'guest_count'         => 110,
                'employer_key'        => 6,
                'contractor_key'      => 2,
                'total_amount'        => 880_000_000,
                'deposit_amount'      => 300_000_000,
                'discount_amount'     => 44_000_000,
                'discount_percentage' => 5,
                'status'              => 'completed',
            ],
            [
                'title'               => 'جلسه هیأت مدیره شرکت سرمایه‌گذاری',
                'description'         => 'جلسه سالانه هیأت مدیره با حضور سهامداران عمده.',
                'program_type'        => 'event',
                'start_date'          => '2026-05-18',
                'end_date'            => '2026-05-19',
                'rooms_allocated'     => 5,
                'guest_count'         => 15,
                'employer_key'        => 8,
                'contractor_key'      => 9,
                'total_amount'        => 75_000_000,
                'deposit_amount'      => 30_000_000,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'status'              => 'completed',
            ],
            [
                'title'               => 'دوره آموزشی تخصصی پرستاری',
                'description'         => 'دوره آموزشی پنج روزه ویژه پرستاران و کادر درمانی با تدریس اساتید دانشگاه علوم پزشکی.',
                'program_type'        => 'event',
                'start_date'          => '2026-09-10',
                'end_date'            => '2026-09-15',
                'rooms_allocated'     => 20,
                'guest_count'         => 55,
                'employer_key'        => 3,
                'contractor_key'      => 3,
                'total_amount'        => 440_000_000,
                'deposit_amount'      => 150_000_000,
                'discount_amount'     => 22_000_000,
                'discount_percentage' => 5,
                'status'              => 'active',
            ],
            [
                'title'               => 'اردوی دانش‌آموزی المپیاد علمی',
                'description'         => 'اسکان دانش‌آموزان برگزیده المپیاد ریاضی و فیزیک کشور در مرحله استانی.',
                'program_type'        => 'camp',
                'start_date'          => '2026-06-22',
                'end_date'            => '2026-06-26',
                'rooms_allocated'     => 12,
                'guest_count'         => 40,
                'employer_key'        => 6,
                'contractor_key'      => 4,
                'total_amount'        => 200_000_000,
                'deposit_amount'      => 60_000_000,
                'discount_amount'     => 10_000_000,
                'discount_percentage' => 5,
                'status'              => 'completed',
            ],
            [
                'title'               => 'مسابقات ورزشی تیم فوتبال لیگ استانی',
                'description'         => 'اسکان تیم‌های ورزشی شرکت‌کننده در مسابقات لیگ استانی فوتبال.',
                'program_type'        => 'other',
                'start_date'          => '2026-07-05',
                'end_date'            => '2026-07-08',
                'rooms_allocated'     => 8,
                'guest_count'         => 28,
                'employer_key'        => 5,
                'contractor_key'      => 8,
                'total_amount'        => 112_000_000,
                'deposit_amount'      => 35_000_000,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'status'              => 'completed',
            ],
            [
                'title'               => 'گردهمایی انجمن صنفی معلمان',
                'description'         => 'گردهمایی سالانه اعضای انجمن صنفی معلمان با موضوع برنامه درسی ملی.',
                'program_type'        => 'event',
                'start_date'          => '2026-10-05',
                'end_date'            => '2026-10-07',
                'rooms_allocated'     => 18,
                'guest_count'         => 50,
                'employer_key'        => 6,
                'contractor_key'      => 1,
                'total_amount'        => 350_000_000,
                'deposit_amount'      => 100_000_000,
                'discount_amount'     => 17_500_000,
                'discount_percentage' => 5,
                'status'              => 'active',
            ],
            [
                'title'               => 'اردوی کمپ تابستانی نوجوانان',
                'description'         => 'کمپ تابستانی ۱۰ روزه برای نوجوانان ۱۳ تا ۱۷ سال با برنامه‌های ورزشی، هنری و علمی.',
                'program_type'        => 'camp',
                'start_date'          => '2026-08-15',
                'end_date'            => '2026-08-25',
                'rooms_allocated'     => 16,
                'guest_count'         => 55,
                'employer_key'        => 4,
                'contractor_key'      => 0,
                'total_amount'        => 550_000_000,
                'deposit_amount'      => 180_000_000,
                'discount_amount'     => 27_500_000,
                'discount_percentage' => 5,
                'status'              => 'active',
            ],
            [
                'title'               => 'جشنواره فرهنگی هنری بین‌المللی',
                'description'         => 'جشنواره چهار روزه هنرهای تجسمی و موسیقی با حضور هنرمندان ایرانی و خارجی.',
                'program_type'        => 'event',
                'start_date'          => '2026-11-12',
                'end_date'            => '2026-11-16',
                'rooms_allocated'     => 22,
                'guest_count'         => 65,
                'employer_key'        => 6,
                'contractor_key'      => 6,
                'total_amount'        => 650_000_000,
                'deposit_amount'      => 200_000_000,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'status'              => 'active',
            ],
            [
                'title'               => 'نشست تخصصی توسعه پایدار شهری',
                'description'         => 'نشست دو روزه متخصصان شهرسازی و محیط زیست.',
                'program_type'        => 'event',
                'start_date'          => '2026-04-08',
                'end_date'            => '2026-04-10',
                'rooms_allocated'     => 10,
                'guest_count'         => 32,
                'employer_key'        => 6,
                'contractor_key'      => 3,
                'total_amount'        => 160_000_000,
                'deposit_amount'      => 50_000_000,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'status'              => 'completed',
            ],
            [
                'title'               => 'اردوی لغو‌شده شرکت صنعتی',
                'description'         => 'اردوی خانوادگی کارکنان که به دلیل تعطیلی کارخانه لغو گردید.',
                'program_type'        => 'camp',
                'start_date'          => '2026-08-20',
                'end_date'            => '2026-08-25',
                'rooms_allocated'     => 20,
                'guest_count'         => 60,
                'employer_key'        => 8,
                'contractor_key'      => 2,
                'total_amount'        => 480_000_000,
                'deposit_amount'      => 100_000_000,
                'discount_amount'     => 0,
                'discount_percentage' => 0,
                'status'              => 'cancelled',
            ],
        ];

        $accList = $accommodations->values();
        $total   = $accList->count();

        $this->command->info("در حال ایجاد برنامه‌های حمایتی...");
        foreach ($supportivePrograms as $i => $data) {
            $acc  = $accList[$i % $total];
            $this->createProgram($acc, $data, $employers, $contractors, true);
        }

        $this->command->info("در حال ایجاد برنامه‌های معمولی...");
        foreach ($regularPrograms as $i => $data) {
            $acc  = $accList[($i + count($supportivePrograms)) % $total];
            $this->createProgram($acc, $data, $employers, $contractors, false);
        }

        $total_programs = count($supportivePrograms) + count($regularPrograms);
        $this->command->info("✔ {$total_programs} برنامه با موفقیت ایجاد شد.");
        $this->command->info("  ↳ " . count($supportivePrograms) . " برنامهٔ خدمات حمایتی بنیاد شهید");
        $this->command->info("  ↳ " . count($regularPrograms)    . " برنامهٔ معمولی");

        // خلاصه مالی خدمات حمایتی
        $supportiveTotal = Program::where('is_supportive_service', true)
            ->where('status', '!=', 'cancelled')
            ->sum('discount_amount');
        $supportiveGuests = Program::where('is_supportive_service', true)
            ->where('status', '!=', 'cancelled')
            ->sum('guest_count');
        $this->command->info("  💰 جمع خدمات حمایتی: " . number_format($supportiveTotal) . " ریال");
        $this->command->info("  👥 تعداد بهره‌مندان: " . number_format($supportiveGuests) . " نفر");
    }

    private function createProgram(Accommodation $acc, array $data, array $employers, array $contractors, bool $isSupportive): void
    {
        $program = Program::create([
            'accommodation_id'        => $acc->id,
            'title'                   => $data['title'],
            'description'             => $data['description'],
            'program_type'            => $data['program_type'],
            'start_date'              => $data['start_date'],
            'end_date'                => $data['end_date'],
            'rooms_allocated'         => $data['rooms_allocated'],
            'guest_count'             => $data['guest_count'],
            'employer'                => $employers[$data['employer_key']],
            'contractor'              => $contractors[$data['contractor_key']],
            'total_amount'            => $data['total_amount'],
            'deposit_amount'          => $data['deposit_amount'],
            'discount_amount'         => $data['discount_amount'],
            'discount_percentage'     => $data['discount_percentage'],
            'is_supportive_service'   => $isSupportive,
            'supportive_service_type' => $isSupportive ? ($data['supportive_service_type'] ?? null) : null,
            'notes'                   => $data['notes'] ?? null,
            'status'                  => $data['status'],
        ]);

        // اتصال اتاق‌های موجود این اقامتگاه به برنامه
        $roomTypes = $acc->roomTypes()->where('is_active', true)->get();
        if ($roomTypes->isNotEmpty()) {
            $sync = [];
            $remaining = $data['rooms_allocated'];

            foreach ($roomTypes->take(min($roomTypes->count(), 3)) as $rt) {
                if ($remaining <= 0) break;
                $allocate = min($remaining, max(1, (int)floor($data['rooms_allocated'] / $roomTypes->count())));
                $allocate = min($allocate, $rt->room_count);
                if ($allocate > 0) {
                    $sync[$rt->id] = ['rooms_count' => $allocate];
                    $remaining -= $allocate;
                }
            }

            if (!empty($sync)) {
                $program->roomTypes()->sync($sync);
            }
        }
    }
}
