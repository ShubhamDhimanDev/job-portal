<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guest is redirected away from the password settings', function () {
    $this->get('/admin/settings/password')->assertRedirect('/login');
    $this->put('/admin/settings/password')->assertRedirect('/login');
});

test('admin can view the password settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/settings/password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/settings/password'));
});

test('admin can change their password with the current password', function () {
    $user = User::factory()->create(['password' => 'old-password-123']);

    $this->actingAs($user)->put('/admin/settings/password', [
        'current_password' => 'old-password-123',
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('new-password-456', $user->refresh()->password))->toBeTrue();
});

test('changing the password requires the correct current password', function () {
    $user = User::factory()->create(['password' => 'old-password-123']);

    $this->actingAs($user)->put('/admin/settings/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password-123', $user->refresh()->password))->toBeTrue();
});

test('the new password must be confirmed and different from the current one', function () {
    $user = User::factory()->create(['password' => 'old-password-123']);

    $this->actingAs($user)->put('/admin/settings/password', [
        'current_password' => 'old-password-123',
        'password' => 'new-password-456',
        'password_confirmation' => 'does-not-match',
    ])->assertSessionHasErrors('password');

    $this->actingAs($user)->put('/admin/settings/password', [
        'current_password' => 'old-password-123',
        'password' => 'old-password-123',
        'password_confirmation' => 'old-password-123',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('old-password-123', $user->refresh()->password))->toBeTrue();
});
