<?php

namespace App\Actions\Proxy;

use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;

class SaveConfiguration
{
    use AsAction;

    public function handle(Server $server, ?string $proxy_settings = null)
    {
        try {
            if (is_null($proxy_settings)) {
                $proxy_settings = CheckConfiguration::run($server, true);
            }
            $proxy_path = $server->proxyPath();

            $proxy_settings = $this->addProxySpecificConfigurations($server, $proxy_settings, $proxy_path);

            $docker_compose_yml_base64 = base64_encode($proxy_settings);

            $server->proxy->last_saved_settings = str($docker_compose_yml_base64)->pipe('md5')->value;
            $server->save();

            $result = instant_remote_process([
                "mkdir -p $proxy_path",
                "echo '$docker_compose_yml_base64' | base64 -d | tee $proxy_path/docker-compose.yml > /dev/null",
            ], $server);

            $this->validateSavedConfiguration($server, $proxy_path);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Error saving proxy configuration: ' . $e->getMessage(), ['server_id' => $server->id]);
            throw $e;
        }
    }

    private function addProxySpecificConfigurations(Server $server, string $proxy_settings, string $proxy_path): string
    {
        if ($server->isSwarm()) {
            return $this->addTraefikConfiguration($proxy_settings);
        } else {
            return $this->addCaddyConfiguration($proxy_settings, $proxy_path);
        }
    }

    private function addTraefikConfiguration(string $proxy_settings): string
    {
        $traefik_config = <<<EOT

        environment:
          - TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_INSECURE=true
          - TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_INSECURE=true
          - TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0
          - TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0
        EOT;

        return $proxy_settings . $traefik_config;
    }

    private function addCaddyConfiguration(string $proxy_settings, string $proxy_path): string
    {
        $caddy_config = <<<EOT
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

        file_put_contents("$proxy_path/Caddyfile", $caddy_config);

        $proxy_settings .= "\n    volumes:\n      - ./Caddyfile:/etc/caddy/Caddyfile";

        return $proxy_settings;
    }

    private function validateSavedConfiguration(Server $server, string $proxy_path): void
    {
        $saved_config = instant_remote_process(["cat $proxy_path/docker-compose.yml"], $server);
        if (empty($saved_config)) {
            throw new \Exception('Failed to save proxy configuration');
        }
    }
}
