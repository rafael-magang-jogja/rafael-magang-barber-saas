<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class OwnerProfileAndPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->owner = User::query()->where('email', 'owner@barberking.id')->firstOrFail();
        $this->tenant = Tenant::query()->firstOrFail();
    }

    public function test_owner_can_view_settings_with_profile_and_password_sections(): void
    {
        $this->actingAs($this->owner)
            ->get(route('owner.settings'))
            ->assertOk()
            ->assertSee('Profil Akun Pemilik (Owner)')
            ->assertSee('Keamanan &amp; Ganti Kata Sandi', false)
            ->assertSee($this->owner->name)
            ->assertSee($this->owner->email);
    }

    public function test_owner_can_update_personal_profile(): void
    {
        $response = $this->actingAs($this->owner)->put(route('owner.profile.update'), [
            'name' => 'Owner Raja Cukur Baru',
            'email' => 'owner.baru@barberking.id',
            'phone' => '081299887766',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status_profile');

        $this->owner->refresh();
        $this->assertSame('Owner Raja Cukur Baru', $this->owner->name);
        $this->assertSame('owner.baru@barberking.id', $this->owner->email);
        $this->assertSame('081299887766', $this->owner->phone);
    }

    public function test_owner_cannot_update_profile_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplikat@barberking.id']);

        $response = $this->actingAs($this->owner)->put(route('owner.profile.update'), [
            'name' => 'Owner Raja Cukur',
            'email' => 'duplikat@barberking.id',
            'phone' => '081234567890',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_owner_can_update_password(): void
    {
        $response = $this->actingAs($this->owner)->put(route('owner.password.update'), [
            'current_password' => 'password',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status_password');

        $this->owner->refresh();
        $this->assertTrue(Hash::check('passwordbaru123', $this->owner->password));
    }

    public function test_owner_cannot_update_password_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->owner)->put(route('owner.password.update'), [
            'current_password' => 'passwordSalahTotal',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_owner_cannot_update_password_if_confirmation_mismatches(): void
    {
        $response = $this->actingAs($this->owner)->put(route('owner.password.update'), [
            'current_password' => 'password',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordBeda',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
