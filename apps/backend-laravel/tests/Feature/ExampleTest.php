<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_redirects_to_product_catalog(): void
    {
        $this->get('/')->assertRedirect('/products');
    }
}
