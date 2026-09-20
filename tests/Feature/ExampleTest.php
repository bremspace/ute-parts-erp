<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Root menuju zona marketplace (katalog produk).
     */
    public function test_root_redirects_to_shop(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/shop');
    }

    public function test_login_page_renders(): void
    {
        $response = $this->get('/app/login');

        $response->assertStatus(200);
        $response->assertSee('UTE PARTS');
    }

    public function test_shop_catalog_renders(): void
    {
        $this->get('/shop')->assertStatus(200)->assertSee('Katalog');
    }
}