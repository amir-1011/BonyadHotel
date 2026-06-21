<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ManualBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingGuestUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
    }

    public function test_new_guest_user_is_created_with_entered_national_id(): void
    {
        $user = $this->resolveGuestUser([
            'booker_national_id'   => '1112223344',
            'guest_contact_name'   => 'علی رضایی',
            'guest_contact_mobile' => '09123456789',
            'veteran_type'         => null,
        ]);

        $this->assertSame('1112223344', $user->national_id);
        $this->assertSame('09123456789', $user->mobile);
        $this->assertSame('علی رضایی', $user->name);
        $this->assertTrue($user->hasRole('guest'));
    }

    public function test_duplicate_mobile_is_rejected_with_helpful_message(): void
    {
        User::create([
            'name'        => 'کاربر قدیمی',
            'mobile'      => '09111111111',
            'national_id' => '2222222222',
        ])->assignRole('guest');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('این شماره موبایل قبلاً ثبت شده است (کد ملی: 2222222222)');

        $this->resolveGuestUser([
            'booker_national_id'   => '3333333333',
            'guest_contact_name'   => 'کاربر جدید',
            'guest_contact_mobile' => '09111111111',
        ]);
    }

    public function test_duplicate_national_id_with_different_mobile_is_rejected(): void
    {
        User::create([
            'name'        => 'کاربر قدیمی',
            'mobile'      => '09222222222',
            'national_id' => '4444444444',
        ])->assignRole('guest');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('کد ملی با شماره موبایل هم‌خوانی ندارد');

        $this->resolveGuestUser([
            'booker_national_id'   => '4444444444',
            'guest_contact_name'   => 'کاربر جدید',
            'guest_contact_mobile' => '09333333333',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function resolveGuestUser(array $data): User
    {
        $service = app(ManualBookingService::class);
        $method = new ReflectionMethod(ManualBookingService::class, 'resolveGuestUser');
        $method->setAccessible(true);

        return $method->invoke($service, $data);
    }
}
