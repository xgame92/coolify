<?php

namespace App\Actions\Proxy;

use App\Events\ProxyStarted;
use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Log;

class StartProxy
{
    use AsAction;

    public function handle(Server $server, bool $async = true, bool $force = false): string|Activity
    {
        try {
            $proxyType = $server->proxyType();
            if ((is_null($proxyType) || $proxyType === 'NONE' || $server->proxy->force_stop || $server->isBuildServer()) && $force === false) {
                return 'OK';
            }
            $commands = collect([]);
            $proxy_path = $server->proxyPath();
            $configuration = CheckConfiguration::run($server);
            if (! $configuration) {
                throw new \Exception('Configuration is not synced');
            }
            SaveConfiguration::run($server, $configuration);
            $docker_compose_yml_base64 = base64_encode($configuration);
            $server->proxy->last_applied_settings = str($docker_compose_yml_base64)->pipe('md5')->value;
            $server->save();

            if ($server->isSwarm()) {
                $commands = $this->getSwarmCommands($proxy_path);
            } else {
                $commands = $this->getNonSwarmCommands($proxy_path, $server);
            }

            if ($async) {
                $activity = remote_process($commands, $server, callEventOnFinish: 'ProxyStarted', callEventData: $server);
                return $activity;
            } else {
                instant_remote_process($commands, $server);
                $this->updateServerProxyStatus($server, $proxyType);
                ProxyStarted::dispatch($server);
                return 'OK';
            }
        } catch (\Throwable $e) {
            Log::error('Error starting proxy: ' . $e->getMessage(), ['server_id' => $server->id]);
            throw $e;
        }
    }

    private function getSwarmCommands(string $proxy_path): array
    {
        return [
            "mkdir -p $proxy_path/dynamic",
            "cd $proxy_path",
            "echo 'Creating required Docker Compose file.'",
            "echo 'Starting coolify-proxy.'",
            'docker stack deploy -c docker-compose.yml coolify-proxy',
            "echo 'Proxy started successfully.'",
            "echo 'Configuring Traefik for proper IP forwarding.'",
            "echo 'TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_INSECURE=true' >> .env",
            "echo 'TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_INSECURE=true' >> .env",
            "echo 'TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0' >> .env",
            "echo 'TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0' >> .env",
        ];
    }

    private function getNonSwarmCommands(string $proxy_path, Server $server): array
    {
        $caddyfile = $this->getCaddyfileContent();
        $commands = [
            "mkdir -p $proxy_path/dynamic",
            "cd $proxy_path",
            "echo '$caddyfile' > $proxy_path/Caddyfile",
            "echo 'Creating required Docker Compose file.'",
            "echo 'Pulling docker image.'",
            'docker compose pull',
            "echo 'Stopping existing coolify-proxy.'",
            'docker stop -t 10 coolify-proxy || true',
            'docker rm coolify-proxy || true',
            "echo 'Starting coolify-proxy.'",
            'docker compose up -d --remove-orphans',
            "echo 'Proxy started successfully.'",
        ];
        return array_merge($commands, connectProxyToNetworks($server));
    }

    private function getCaddyfileContent(): string
    {
        return <<<EOT
{
    auto_https off
    servers {
        trusted_proxies static private_ranges
    }
    log {
        output stdout
        format json
        level DEBUG
    }
}
import /dynamic/*.caddy
EOT;
    }

    private function updateServerProxyStatus(Server $server, string $proxyType): void
    {
        $server->proxy->set('status', 'running');
        $server->proxy->set('type', $proxyType);
        $server->save();
    }
}
