<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\RoomType;
use App\Models\RoomRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RichAccommodationSeeder extends Seeder
{
    public function run(): void
    {
        $accommodations = Accommodation::all();

        foreach ($accommodations as $acc) {
            $dir = "public/accommodations/{$acc->id}";
            $publicUrls = [];

            if (!function_exists('imagecreatetruecolor')) {
                // GD not available — skip image generation but ensure arrays exist
                $acc->update(['images' => [], 'image' => null]);
            } else {
                if (!is_dir(storage_path("app/{$dir}"))) {
                    mkdir(storage_path("app/{$dir}"), 0777, true);
                }

                $count = rand(3, 6);
                for ($i = 1; $i <= $count; $i++) {
                    $w = 1200; $h = 800;
                    $img = imagecreatetruecolor($w, $h);
                    $bg = imagecolorallocate($img, rand(40, 220), rand(40, 220), rand(40, 220));
                    imagefilledrectangle($img, 0, 0, $w, $h, $bg);

                    $text = mb_substr($acc->name, 0, 30) . " — تصویر {$i}";
                    $textColor = imagecolorallocate($img, 255, 255, 255);
                    imagestring($img, 5, 20, 20, $text, $textColor);

                    $filename = "img{$i}.jpg";
                    $fullPath = storage_path("app/{$dir}/{$filename}");
                    imagejpeg($img, $fullPath, 85);
                    imagedestroy($img);

                    $publicUrls[] = "accommodations/{$acc->id}/{$filename}";
                }

                $acc->update(['images' => $publicUrls, 'image' => $publicUrls[0] ?? null]);
            }

            // Create 1-4 room types for each accommodation
            $roomTypeCount = rand(1, 4);
            for ($rt = 1; $rt <= $roomTypeCount; $rt++) {
                $rtName = ("{$acc->name} - اتاق " . $rt);
                $roomType = RoomType::create([
                    'accommodation_id' => $acc->id,
                    'name' => $rtName,
                    'description' => "اتاق نمونه برای {$acc->name}",
                    'bed_type' => array_rand(['single' => 1, 'double' => 1, 'sofa' => 1]),
                    'capacity' => rand(1, max(1, $acc->capacity)),
                    'size_sqm' => rand(12, 60),
                    'smoking' => rand(0,1),
                    'has_private_bathroom' => (bool)rand(0,1),
                    'images' => $publicUrls ? [$publicUrls[array_rand($publicUrls)]] : [],
                    'amenities' => [],
                    'room_count' => rand(1, 6),
                    'sort_order' => $rt,
                    'is_active' => true,
                ]);

                // Add 1-3 rates per room type
                $rateCount = rand(1,3);
                for ($r = 1; $r <= $rateCount; $r++) {
                    RoomRate::create([
                        'room_type_id' => $roomType->id,
                        'name' => "قیمت استاندارد {$r}",
                        'price_per_night' => max(100000, intval($acc->price_per_night * (0.8 + rand(0,40)/100))),
                        'breakfast_included' => (bool)rand(0,1),
                        'breakfast_price_per_person' => rand(0, 120000),
                        'cancellation_policy' => rand(0,1) ? 'free' : 'non_refundable',
                        'payment_type' => rand(0,1) ? 'pay_at_hotel' : 'prepay_online',
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
