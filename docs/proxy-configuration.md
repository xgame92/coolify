# Proxy Configuration

This document outlines the changes made to improve IP forwarding in both Traefik and Caddy proxy configurations.

## Traefik Configuration

For Swarm setups, we've added the following environment variables to ensure proper IP forwarding:

- `TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_INSECURE=true`
- `TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_INSECURE=true`
- `TRAEFIK_ENTRYPOINTS_HTTP_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0`
- `TRAEFIK_ENTRYPOINTS_HTTPS_FORWARDEDHEADERS_TRUSTEDIPS=0.0.0.0/0`

These settings allow Traefik to trust forwarded headers from all IP ranges, which is necessary for proper IP forwarding in our setup.

## Caddy Configuration

For non-Swarm setups, we've updated the Caddyfile with the following configuration:

```
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
```

This configuration trusts private IP ranges as proxies and enables debug logging for easier troubleshooting.

## Debugging

We've added a new `LogHeaders` middleware an
