<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pengujian Dasar Halaman Utama & Login (ExampleTest)
 */
class ExampleTest extends TestCase
{
    public function test_the_application_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_login_page_returns_a_successful_response(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }
}
