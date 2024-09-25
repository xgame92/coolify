<?php

namespace Tests\Feature;

use App\Actions\Proxy\StartProxy;
use App\Actions\Proxy\SaveConfiguration;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxy_configuration_and_startup_flow()
    {
        $server = Server::factory()->create(['is_swarm' => false]);

        // Save configuration
        $result = SaveConfiguration::run($server, 'test config');
        $this->assertNotEmpty($result);

        // Start proxy
        $startResult = StartProxy::run($server, false);
        $this->assertEquals('OK', $startResult);

        // Verify server status
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'proxy->status' => 'running',
            'proxy->type' => $server->proxyType(),
        ]);

        // Test IP forwarding
        $response = $this->get('/api/test-ip');
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'ip',
                     'xForwardedFor',
                     'xRealIp',
                     'remoteAddr'
                 ]);
    }
}
