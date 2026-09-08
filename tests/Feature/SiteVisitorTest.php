<?php

namespace Tests\Feature;

test('test_visitor_is_redirected_to_site', function () {
    $response = $this->post('/go_to_site', [
        'multi_input' => 'glcxf7sv5minwco4dbt5bizxmdk3vub7ohhzkkt6e7iurzj4dpzq',
    ], ['Priority' => 'u=0, i']);

    $response->assertRedirectContains('http://glcxf7sv5minwco4dbt5bizxmdk3vub7ohhzkkt6e7iurzj4dpzq');
});
