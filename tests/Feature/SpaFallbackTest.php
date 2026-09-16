<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaFallbackTest extends TestCase
{
    public function test_spa_entrypoint_is_served_for_unknown_paths(): void
    {
        $response = $this->get('/settings');

        $response->assertOk();
        $response->assertViewIs('app');
    }
}
