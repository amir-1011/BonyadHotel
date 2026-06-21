<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use Illuminate\Database\Seeder;

class VeteranPolicySeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'key'                    => 'veteran_70_spouses',
                'label'                  => 'جانبازان ۷۰ درصد و همسران',
                'accommodation_discount' => 70,
                'weekly_free_sessions'   => 3,
                'usage_notes'            => '۶ شب به ازای هر نفر تحت تکفل (سقف ۳ شب در هر ۶ ماه)',
                'sort_order'             => 1,
            ],
            [
                'key'                    => 'veteran_50_69_dependents',
                'label'                  => 'جانبازان ۵۰ الی ۶۹ درصد به همراه افراد تحت تکفل',
                'accommodation_discount' => 50,
                'sort_order'             => 2,
            ],
            [
                'key'                    => 'veteran_25_49_dependents',
                'label'                  => 'جانبازان ۵ الی ۴۹ درصد به همراه افراد تحت تکفل',
                'accommodation_discount' => 40,
                'sort_order'             => 3,
            ],
            [
                'key'                    => 'martyr_children',
                'label'                  => 'فرزندان شهدا و فرزندان جانبازان ۷۰ درصد',
                'accommodation_discount' => 50,
                'sort_order'             => 4,
            ],
            [
                'key'                    => 'martyr_parents_dependents',
                'label'                  => 'والدین شهدا به همراه افراد تحت تکفل',
                'accommodation_discount' => 70,
                'sort_order'             => 5,
            ],
            [
                'key'                    => 'martyr_spouse_dependents',
                'label'                  => 'همسر شهید به همراه افراد تحت تکفل',
                'accommodation_discount' => 50,
                'sort_order'             => 6,
            ],
            [
                'key'                    => 'freed_prisoner_dependents',
                'label'                  => 'آزادگان سرافراز به همراه افراد تحت تکفل',
                'accommodation_discount' => 50,
                'sort_order'             => 7,
            ],
        ];

        foreach ($groups as $data) {
            VeteranGroup::updateOrCreate(
                ['key' => $data['key']],
                array_merge([
                    'nights_per_dependent'  => 6,
                    'max_nights_per_period' => 3,
                    'period_months'         => 6,
                    'is_active'             => true,
                ], $data)
            );
        }

        $services = [
            [
                'key'                    => 'pool',
                'name'                   => 'استخر',
                'default_price'          => 0,
                'supports_free_sessions' => true,
                'default_discount'       => 65,
                'min_discount'           => 50,
                'max_discount'           => 80,
                'sort_order'             => 1,
            ],
            [
                'key'                    => 'gym',
                'name'                   => 'بدنسازی',
                'default_price'          => 0,
                'supports_free_sessions' => true,
                'default_discount'       => 65,
                'min_discount'           => 50,
                'max_discount'           => 80,
                'sort_order'             => 2,
            ],
            [
                'key'                    => 'multi_purpose_hall',
                'name'                   => 'سالن چند منظوره',
                'default_price'          => 0,
                'supports_free_sessions' => true,
                'default_discount'       => 65,
                'min_discount'           => 50,
                'max_discount'           => 80,
                'sort_order'             => 3,
            ],
            [
                'key'              => 'conference_hall',
                'name'             => 'سالن همایش',
                'default_price'    => 0,
                'default_discount' => 40,
                'sort_order'       => 4,
            ],
            [
                'key'              => 'reception_entrance',
                'name'             => 'تالار پذیرایی — ورودی',
                'default_price'    => 0,
                'default_discount' => 50,
                'sort_order'       => 5,
            ],
            [
                'key'              => 'reception_food',
                'name'             => 'تالار پذیرایی — غذا',
                'default_price'    => 0,
                'default_discount' => 20,
                'sort_order'       => 6,
            ],
        ];

        foreach ($services as $data) {
            ServiceCatalog::updateOrCreate(
                ['key' => $data['key']],
                array_merge(['is_active' => true], $data)
            );
        }

        $this->seedServiceDiscountMatrix();
    }

    private function seedServiceDiscountMatrix(): void
    {
        $veteran70 = VeteranGroup::where('key', 'veteran_70_spouses')->first();
        $sportServices = ServiceCatalog::whereIn('key', ['pool', 'gym', 'multi_purpose_hall'])->get();
        $fixedServices = [
            'conference_hall'     => 40,
            'reception_entrance'  => 50,
            'reception_food'      => 20,
        ];

        foreach (VeteranGroup::all() as $group) {
            foreach ($sportServices as $service) {
                $is70 = $group->key === 'veteran_70_spouses';
                VeteranGroupServiceDiscount::updateOrCreate(
                    [
                        'veteran_group_id'  => $group->id,
                        'service_catalog_id' => $service->id,
                    ],
                    [
                        'discount_percentage'    => $is70 ? 0 : 65,
                        'free_sessions_eligible' => $is70,
                        'weekly_free_sessions'   => $is70 ? 3 : 0,
                    ]
                );
            }

            foreach ($fixedServices as $serviceKey => $discount) {
                $service = ServiceCatalog::where('key', $serviceKey)->first();
                if (!$service) {
                    continue;
                }
                VeteranGroupServiceDiscount::updateOrCreate(
                    [
                        'veteran_group_id'   => $group->id,
                        'service_catalog_id' => $service->id,
                    ],
                    [
                        'discount_percentage'    => $discount,
                        'free_sessions_eligible' => false,
                    ]
                );
            }
        }

        unset($veteran70);
    }
}
