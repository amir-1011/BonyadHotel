<?php

namespace Tests\Unit;

use App\Models\FacilityItemBrand;
use App\Models\FacilityItemCategory;
use App\Models\User;
use App\Services\FacilityItemBrandCatalogService;
use App\Services\FacilityItemCategoryCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacilityItemCatalogServicesTest extends TestCase
{
    use RefreshDatabase;

    private FacilityItemCategoryCatalogService $categoryService;

    private FacilityItemBrandCatalogService $brandService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryService = app(FacilityItemCategoryCatalogService::class);
        $this->brandService = app(FacilityItemBrandCatalogService::class);
    }

    public function test_category_service_seeds_default_names(): void
    {
        $this->categoryService->ensureSeeded();

        $names = FacilityItemCategory::query()->orderBy('sort_order')->pluck('name')->all();

        $this->assertSame(FacilityItemCategoryCatalogService::DEFAULT_NAMES, $names);
    }

    public function test_category_seed_is_idempotent(): void
    {
        $this->categoryService->ensureSeeded();
        $countAfterFirst = FacilityItemCategory::query()->count();

        $this->categoryService->ensureSeeded();

        $this->assertSame($countAfterFirst, FacilityItemCategory::query()->count());
    }

    public function test_category_normalize_fixes_arabic_yeh_and_spaces(): void
    {
        $normalized = $this->categoryService->normalize('  برند  يك   ');

        $this->assertSame('برند یک', $normalized);
    }

    public function test_category_add_rejects_empty_name(): void
    {
        $this->expectException(ValidationException::class);

        $this->categoryService->add('   ');
    }

    public function test_category_add_rejects_too_long_name(): void
    {
        $this->expectException(ValidationException::class);

        $this->categoryService->add(str_repeat('ا', 101));
    }

    public function test_category_add_returns_existing_on_duplicate(): void
    {
        $this->categoryService->ensureSeeded();
        $existing = FacilityItemCategory::query()->where('name', 'تجهیزات')->firstOrFail();

        $result = $this->categoryService->add('تجهیزات');

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(3, FacilityItemCategory::query()->count());
    }

    public function test_category_add_creates_new_with_incremented_sort_order(): void
    {
        $this->categoryService->ensureSeeded();

        $new = $this->categoryService->add('دسته جدید');

        $this->assertSame('دسته جدید', $new->name);
        $this->assertGreaterThan(
            (int) FacilityItemCategory::query()->where('name', 'تجهیزات')->value('sort_order'),
            $new->sort_order,
        );
    }

    public function test_brand_add_normalizes_and_stores_creator(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        $user = User::create(['name' => 'میزبان', 'mobile' => '09120000100']);
        $user->assignRole('host');

        $brand = $this->brandService->add('برند  يك', $user->id);

        $this->assertSame('برند یک', $brand->name);
        $this->assertSame($user->id, $brand->created_by);
    }

    public function test_brand_add_returns_existing_instead_of_duplicate(): void
    {
        $first = $this->brandService->add('برند مشترک');
        $second = $this->brandService->add('برند مشترک');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, FacilityItemBrand::query()->where('name', 'برند مشترک')->count());
    }

    public function test_brand_add_rejects_empty_name(): void
    {
        $this->expectException(ValidationException::class);

        $this->brandService->add('');
    }

    public function test_all_ordered_returns_sorted_categories(): void
    {
        $this->categoryService->ensureSeeded();
        FacilityItemCategory::query()->create(['name' => 'ز', 'sort_order' => 99]);

        $names = $this->categoryService->allOrdered()->pluck('name')->all();

        $this->assertSame('تاسیسات', $names[0]);
        $this->assertContains('ز', $names);
    }
}
