<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_static_material_calculator_page_is_available()
    {
        $response = $this->get('/client/material-calculator');

        $response->assertStatus(200);
    }
}
