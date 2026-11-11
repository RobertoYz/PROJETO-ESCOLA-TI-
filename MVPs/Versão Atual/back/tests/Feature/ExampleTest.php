<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Valida que a API REST está operacional através do endpoint de editais.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->getJson('/api/editais');

        $response->assertStatus(200);
    }
}
