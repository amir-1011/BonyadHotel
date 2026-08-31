<?php

namespace Tests\Unit;

use App\Models\FacilityExchangeItem;
use App\Models\User;
use App\Services\FacilityExchangeItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithFacilityExchange;
use Tests\TestCase;

class FacilityExchangeItemServiceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFacilityExchange;

    private FacilityExchangeItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->setupFacilityExchangeContext();
        $this->service = app(FacilityExchangeItemService::class);
    }

    public function test_create_surplus_stores_webp_image(): void
    {
        $image = $this->fakeFacilityImage();

        $item = $this->service->create(
            FacilityExchangeItem::TYPE_SURPLUS,
            $this->validSurplusPayload(),
            $this->facilityHost,
            [$image],
        );

        $this->assertNotNull($item->image_path);
        $this->assertStringEndsWith('.webp', $item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
        $this->assertSame([$item->image_path], $item->imagePaths());
    }

    public function test_create_needed_never_stores_image_even_if_provided(): void
    {
        $image = $this->fakeFacilityImage();

        $item = $this->service->create(
            FacilityExchangeItem::TYPE_NEEDED,
            $this->validSurplusPayload(),
            $this->facilityHost,
            [$image],
        );

        $this->assertNull($item->image_path);
        $this->assertSame([], $item->imagePaths());
    }

    public function test_update_replaces_image_and_deletes_previous_file(): void
    {
        $item = $this->makeSurplusItem([
            'image_path' => 'facility-exchange/old.webp',
            'image_paths' => ['facility-exchange/old.webp'],
        ]);
        Storage::disk('public')->put('facility-exchange/old.webp', 'old');

        $newImage = $this->fakeFacilityImage('new.jpg');
        $updated = $this->service->update($item, $this->validSurplusPayload(), [$newImage], []);

        Storage::disk('public')->assertMissing('facility-exchange/old.webp');
        $this->assertNotSame('facility-exchange/old.webp', $updated->image_path);
        Storage::disk('public')->assertExists($updated->image_path);
    }

    public function test_update_remove_image_deletes_file(): void
    {
        $item = $this->makeSurplusItem([
            'image_path' => 'facility-exchange/remove.webp',
            'image_paths' => ['facility-exchange/remove.webp'],
        ]);
        Storage::disk('public')->put('facility-exchange/remove.webp', 'data');

        $updated = $this->service->update(
            $item,
            $this->validSurplusPayload(),
            [],
            [],
        );

        $this->assertNull($updated->image_path);
        $this->assertSame([], $updated->imagePaths());
        Storage::disk('public')->assertMissing('facility-exchange/remove.webp');
    }

    public function test_update_needed_item_clears_image_path(): void
    {
        $item = $this->makeNeededItem();

        $updated = $this->service->update($item, array_merge($this->validSurplusPayload(), [
            'name' => 'به‌روز شده',
        ]));

        $this->assertNull($updated->image_path);
        $this->assertSame('به‌روز شده', $updated->name);
    }

    public function test_delete_removes_record_and_image_file(): void
    {
        $item = $this->makeSurplusItem([
            'image_path' => 'facility-exchange/delete.webp',
            'image_paths' => ['facility-exchange/delete.webp'],
        ]);
        Storage::disk('public')->put('facility-exchange/delete.webp', 'data');

        $this->service->delete($item);

        $this->assertDatabaseMissing('facility_exchange_items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing('facility-exchange/delete.webp');
    }

    public function test_create_persists_expiry_date(): void
    {
        $item = $this->service->create(
            FacilityExchangeItem::TYPE_NEEDED,
            array_merge($this->validSurplusPayload(), [
                'expiry_date' => '2025-09-06',
            ]),
            $this->facilityHost,
        );

        $this->assertSame('2025-09-06', $item->expiry_date?->toDateString());
    }

    public function test_create_surplus_stores_multiple_images(): void
    {
        $images = [
            $this->fakeFacilityImage('one.jpg'),
            $this->fakeFacilityImage('two.jpg'),
        ];

        $item = $this->service->create(
            FacilityExchangeItem::TYPE_SURPLUS,
            $this->validSurplusPayload(),
            $this->facilityHost,
            $images,
        );

        $this->assertCount(2, $item->imagePaths());
        foreach ($item->imagePaths() as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }
}
