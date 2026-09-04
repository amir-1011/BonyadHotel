<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminUserFilter;
use App\Support\AdminUserRoleFilterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserRoleFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
    }

    public function test_role_filter_options_match_table_role_labels(): void
    {
        $host = User::create([
            'name'                 => 'میزبان مالی',
            'mobile'               => '09120001001',
            'host_position_title'  => 'مدیر مالی',
        ]);
        $host->assignRole('host');

        User::create(['name' => 'میزبان ساده', 'mobile' => '09120001002'])->assignRole('host');
        User::create(['name' => 'ادمین', 'mobile' => '09120001003'])->assignRole('super_admin');
        User::create(['name' => 'بدون نقش', 'mobile' => '09120001004']);

        $options = AdminUserRoleFilterCatalog::options();
        $labels = array_column($options, 'label');
        $values = array_column($options, 'value');

        $this->assertContains('مدیر مالی', $labels);
        $this->assertContains('کاربر', $labels);
        $this->assertContains('ادمین', $labels);
        $this->assertContains('مهمان', $labels);
        $this->assertContains('host', $values);
        $this->assertContains('host_position:مدیر مالی', $values);
    }

    public function test_role_filter_can_filter_by_host_position_label(): void
    {
        $target = User::create([
            'name'                => 'میزبان هدف',
            'mobile'              => '09120002001',
            'host_position_title' => 'کارشناس فروش',
        ]);
        $target->assignRole('host');

        $other = User::create([
            'name'                => 'میزبان دیگر',
            'mobile'              => '09120002002',
            'host_position_title' => 'مدیر داخلی',
        ]);
        $other->assignRole('host');

        $query = User::query();
        AdminUserFilter::make(['role' => 'host_position:کارشناس فروش'])->apply($query);
        $ids = $query->pluck('id')->all();

        $this->assertSame([$target->id], $ids);
    }

    public function test_role_filter_finds_users_with_guest_role(): void
    {
        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120003001']);
        $guest->assignRole('guest');

        $host = User::create(['name' => 'میزبان', 'mobile' => '09120003002']);
        $host->assignRole('host');

        $query = User::query();
        AdminUserFilter::make(['role' => 'guest'])->apply($query);

        $this->assertSame([$guest->id], $query->pluck('id')->all());
    }

    public function test_role_filter_finds_users_without_any_role(): void
    {
        $guest = User::create(['name' => 'بدون نقش', 'mobile' => '09120003003']);

        User::create(['name' => 'میزبان', 'mobile' => '09120003004'])->assignRole('host');

        $query = User::query();
        AdminUserFilter::make(['role' => 'guest'])->apply($query);

        $this->assertSame([$guest->id], $query->pluck('id')->all());
    }

    public function test_user_index_livewire_shows_guest_users_when_filtered(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09120004001']);
        $admin->assignRole('super_admin');

        $guest = User::create(['name' => 'مهمان فیلتر', 'mobile' => '09120004002']);
        $guest->assignRole('guest');

        User::create(['name' => 'میزبان', 'mobile' => '09120004003'])->assignRole('host');

        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Livewire\Admin\UserIndex::class)
            ->call('setSection', 'users')
            ->assertSet('section', 'users')
            ->assertSee('مهمان فیلتر')
            ->assertDontSee('09120004003')
            ->assertSeeHtml('wire:click="setSection(\'users\')" class="nav-link py-1 px-2 small active');
    }

    public function test_all_section_lists_users_without_province_groups(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09120004011']);
        $admin->assignRole('super_admin');

        User::create(['name' => 'مهمان همه', 'mobile' => '09120004012'])->assignRole('guest');
        User::create(['name' => 'میزبان همه', 'mobile' => '09120004013'])->assignRole('host');

        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Livewire\Admin\UserIndex::class)
            ->call('setSection', 'all')
            ->assertSet('section', 'all')
            ->assertSee('مهمان همه')
            ->assertSee('میزبان همه')
            ->assertDontSeeHtml('admin-users-province-header')
            ->assertSeeHtml('wire:click="setSection(\'all\')" class="nav-link py-1 px-2 small active');
    }

    public function test_personnel_tabs_start_with_all_personnel(): void
    {
        User::create(['name' => 'میزبان ساده', 'mobile' => '09120005001'])->assignRole('host');

        $finance = User::create([
            'name'                => 'میزبان مالی',
            'mobile'              => '09120005002',
            'host_position_title' => 'مدیر مالی',
        ]);
        $finance->assignRole('host');

        $options = AdminUserRoleFilterCatalog::personnelTabOptions();

        $this->assertSame(AdminUserRoleFilterCatalog::ALL_PERSONNEL, $options[0]['value']);
        $this->assertSame('همه پرسنل', $options[0]['label']);
        $this->assertContains('کاربران', array_column($options, 'label'));
        $this->assertContains('مدیر مالی', array_column($options, 'label'));
    }

    public function test_all_personnel_filter_includes_every_host_position(): void
    {
        $defaultHost = User::create(['name' => 'میزبان پیش‌فرض', 'mobile' => '09120005011']);
        $defaultHost->assignRole('host');

        $finance = User::create([
            'name'                => 'میزبان مالی',
            'mobile'              => '09120005012',
            'host_position_title' => 'مدیر مالی',
        ]);
        $finance->assignRole('host');

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09120005013']);
        $guest->assignRole('guest');

        $query = User::query();
        AdminUserFilter::make(['role' => AdminUserRoleFilterCatalog::ALL_PERSONNEL])->apply($query);
        $ids = $query->orderBy('id')->pluck('id')->all();

        $this->assertSame([$defaultHost->id, $finance->id], $ids);
    }

    public function test_personnel_section_defaults_to_all_personnel_tab(): void
    {
        $admin = User::create(['name' => 'ادمین', 'mobile' => '09120005021']);
        $admin->assignRole('super_admin');

        User::create(['name' => 'میزبان پیش‌فرض', 'mobile' => '09120005022'])->assignRole('host');

        $finance = User::create([
            'name'                => 'میزبان مالی فیلتر',
            'mobile'              => '09120005023',
            'host_position_title' => 'مدیر مالی',
        ]);
        $finance->assignRole('host');

        User::create(['name' => 'مهمان مخفی', 'mobile' => '09120005024'])->assignRole('guest');

        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Livewire\Admin\UserIndex::class)
            ->call('setSection', 'personnel')
            ->assertSet('section', 'personnel')
            ->assertSet('role', AdminUserRoleFilterCatalog::ALL_PERSONNEL)
            ->assertSee('همه پرسنل')
            ->assertSee('میزبان پیش‌فرض')
            ->assertSee('میزبان مالی فیلتر')
            ->assertDontSee('مهمان مخفی')
            ->call('setRoleTab', 'host_position:مدیر مالی')
            ->assertSet('role', 'host_position:مدیر مالی')
            ->assertSee('میزبان مالی فیلتر')
            ->assertDontSee('09120005022');
    }
}
