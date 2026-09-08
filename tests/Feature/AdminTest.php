<?php

namespace Tests\Feature;

use App\Models\Admin;

beforeEach(function () {
    Admin::createDummyAdminForTests();
    $this->actingAs(Admin::first(), 'admin')->get('/admin/dashboard')->assertStatus(200);
});

test('Dashboard', function () {
    expect($this->get('/admin/dashboard')->getContent())->toContain('Serveronet Version');
});

test('developed_sites', function () {
    expect($this->get('/admin/developed_sites')->getContent())->toContain('Place your Sites in the Developed Sites Directory:');
});

test('Sites Administration', function () {
    expect($this->get('/admin/admin_sites')->getContent())->toContain('Sites Administration');
});

test('control_panel', function () {
    expect($this->get('/admin/control_panel')->getContent())->toContain('Main Loop');
});
