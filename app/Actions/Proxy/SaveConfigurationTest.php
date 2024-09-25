<?php

namespace Tests\Unit\Actions\Proxy;

use App\Actions\Proxy\SaveConfiguration;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_traefik_config_for_swarm_server()
    {
        $server = Server::factory()->create(['is_swarm' => true]);
        $result = SaveConfiguration::run($server, 'existing config');

        $this->assertStringContainsString('TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_INSECURE=true', $result);
        $this->assertStringContainsString('TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_INSECURE=true', $result);
        $this->assertStringContainsString('TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0', $result);
        $this->assertStringContainsString('TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0', $result);
    }

    public function test_it_adds_caddy_config_for_non_swarm_server()
    {
        $server = Server::factory()->create(['is_swarm' => false]);
        $result = SaveConfiguration::run($server, 'existing config');

        $this->assertStringContainsString('volumes:', $result);
        $this->assertStringContainsString('./Caddyfile:/etc/caddy/Caddyfile', $result);
    }

    public function test_it_throws_exception_when_configuration_save_fails()
    {
        $server = Server::factory()->create();
        $this->mock(\Illuminate\Support\Facades\Process::class)
            ->shouldReceive('run')
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to save proxy configuration');

        SaveConfiguration::run($server, 'existing config');
    }
}
