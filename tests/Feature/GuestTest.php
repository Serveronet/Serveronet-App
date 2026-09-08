<?php

namespace Tests\Feature;

test('Dashboard', function () {
    expect($this->get('/')->getContent())->toContain('Welcome to the Serveronet Client');
});

test('Sites', function () {
    expect($this->get('/sites')->getContent())->toContain('Serveronet Sites');
});
