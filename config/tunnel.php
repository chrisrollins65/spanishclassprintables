<?php

/*
 * The development tunnel: how this machine gets a public https address, so
 * that webhooks — Paddle's, for now — can reach a laptop behind a router.
 *
 * Cloudflare runs it (`cloudflared`), which is a deliberate choice over
 * borrowing the production server's address: a tunnel credential authorizes
 * serving one hostname and nothing else, where an SSH key into that box would
 * be a login to the machine running the shop. Cloudflare is already this
 * domain's DNS, so it is not a new party either way.
 *
 * `php artisan tunnel` runs it; `php artisan tunnel:mode` decides what it
 * lets through, by rewriting the ingress rules HERE rather than on a server.
 */
return [
    'binary' => env('TUNNEL_CLOUDFLARED', 'C:\Program Files (x86)\cloudflared\cloudflared.exe'),

    /*
     * The named tunnel and the hostname routed to it (cloudflared tunnel
     * create / route dns). Empty domain means nothing has been set up yet.
     *
     * The name carries this machine's, because a tunnel credential belongs to
     * one machine: two machines running the same tunnel both connect, and
     * Cloudflare then sends each request to whichever it likes — a webhook
     * that lands on the wrong laptop half the time. Setting a second machine
     * up repoints the hostname at its own tunnel, which is what you want:
     * one machine receiving webhooks, and no doubt about which.
     */
    'name' => env('TUNNEL_NAME', 'scp-dev-'.strtolower((string) (gethostname() ?: 'local'))),
    'domain' => env('TUNNEL_DOMAIN', ''),

    // Laragon serves every site on 80 and chooses by name, so the tunnel says
    // which one this is rather than passing on the public hostname.
    'local_url' => env('TUNNEL_LOCAL_URL', 'http://localhost:80'),
    'local_host' => env('TUNNEL_LOCAL_HOST', 'spanishclassprintables.test'),

    /*
     * What the public address answers. Written into the ingress rules, so a
     * path that is not listed never reaches this machine at all.
     *
     * `hooks` is the resting state: each of these defends itself with a
     * signature, and nothing else is anyone's business.
     */
    'webhook_paths' => ['/api/paddle/webhook'],

    // Where the generated config lives. Kept out of the repo: it names a
    // credentials file that is specific to this machine.
    'config_path' => env('TUNNEL_CONFIG', storage_path('app/tunnel/config.yml')),
];
