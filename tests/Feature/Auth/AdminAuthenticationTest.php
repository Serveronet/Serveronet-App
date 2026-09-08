<?php

namespace Tests\Feature\Auth;

use App\Http\Consts;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

test('test_login_screen_can_be_rendered', function () {
    $response = $this->get('/admin/admin_login');

    expect($response->status())->toBe(200);
    expect($response->getContent())->toContain('<html');
    expect($response->getContent())->toContain('Client Administrator login');
});

test('test_users_can_authenticate_using_the_login_screen', function () {
    Admin::create([
        'name' => 'admin',
        'password' => Hash::make(Consts::passwordForTests),
    ]);

    $response = $this->followingRedirects()->post('/admin/admin_login', [
        'name' => 'admin',
        'password' => Consts::passwordForTests,
    ]);

    $this->expect($response->getContent())->toContain('<html');
    $this->expect($response->getContent())->toContain('Admin Dashboard');
});

test('test_users_can_not_authenticate_with_invalid_password', function () {
    Admin::create([
        'name' => 'admin',
        'password' => Hash::make(Consts::passwordForTests),
    ]);

    $this->post('/admin/admin_login', [
        'name' => 'admin',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('admin');
});
