<?php

namespace Tests\Unit\Actions\Proxy;

use App\Actions\Proxy\StartProxy;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_traefik_config_for_swarm_server()
    {
        $server = Server::factory()->create(['is_swarm' => true]);
        $result = StartProxy::run($server, false);

        $this->assertEquals('OK', $result);
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'proxy->status' => 'running',
            'proxy->type' => $server->proxyType(),
        ]);
    }

    public function test_it_adds_caddy_config_for_non_swarm_server()
    {
        $server = Server::factory()->create(['is_swarm' => false]);
        $result = StartProxy::run($server, false);

        $this->assertEquals('OK', $result);
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'proxy->status' => 'running',
            'proxy->type' => $server->proxyType(),
        ]);
    }

    public function test_it_returns_ok_when_proxy_is_none()
    {
        $server = Server::factory()->create(['proxy->type' => 'NONE']);
        $result = StartProxy::run($server, false);

        $this->assertEquals('OK', $result);
    }

    public function test_it_throws_exception_when_configuration_is_not_synced()
    {
        $server = Server::factory()->create();
        $this->mock(\App\Actions\Proxy\CheckConfiguration::class)
            ->shouldReceive('run')
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Configuration is not synced');

        StartProxy::run($server, false);
    }
}
