<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationStatusTest extends TestCase
{
    public function test_root_returns_api_status_without_a_frontend_build(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertExactJson([
                'name' => 'Wealth Portfolio Tracker API',
                'status' => 'ok',
            ]);
    }
}
