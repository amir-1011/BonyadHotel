<?php

namespace Tests\Feature;

use App\Livewire\Host\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
    }

    public function test_host_can_change_password_with_current_password(): void
    {
        $host = User::create([
            'name'     => 'میزبان',
            'mobile'   => '09100000001',
            'password' => 'old-secret',
        ]);
        $host->assignRole('host');

        $this->actingAs($host);

        Livewire::test(Profile::class)
            ->set('currentPassword', 'old-secret')
            ->set('password', 'new-secret')
            ->set('password_confirmation', 'new-secret')
            ->call('changePassword')
            ->assertHasNoErrors();

        $host->refresh();
        $this->assertTrue(Hash::check('new-secret', $host->password));
    }

    public function test_host_cannot_change_password_with_wrong_current_password(): void
    {
        $host = User::create([
            'name'     => 'میزبان',
            'mobile'   => '09100000002',
            'password' => 'old-secret',
        ]);
        $host->assignRole('host');

        $this->actingAs($host);

        Livewire::test(Profile::class)
            ->set('currentPassword', 'wrong-secret')
            ->set('password', 'new-secret')
            ->set('password_confirmation', 'new-secret')
            ->call('changePassword')
            ->assertHasErrors(['currentPassword']);

        $host->refresh();
        $this->assertTrue(Hash::check('old-secret', $host->password));
    }
}
