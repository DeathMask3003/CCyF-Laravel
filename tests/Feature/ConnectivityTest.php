<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConnectivityTest extends TestCase
{
    public function test_guest_can_check_server_connection_without_cached_content(): void
    {
        $response = $this->get('/estado-conexion')->assertNoContent();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
