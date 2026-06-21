<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_national_id_shows_validation_error(): void
    {
        User::create([
            'name'        => 'کاربر اول',
            'mobile'      => '0923983650',
            'national_id' => '0923983650',
        ]);

        $user = User::create([
            'name'   => null,
            'mobile' => '09032512253',
        ]);

        $response = $this->actingAs($user)->post(route('profile.setup.save'), [
            'name'        => 'Ali Hosseini',
            'national_id' => '0923983650',
        ]);

        $response->assertSessionHasErrors('national_id');
        $this->assertStringContainsString(
            'این کد ملی قبلاً برای حساب دیگری ثبت شده است',
            session('errors')->first('national_id')
        );
    }
}
