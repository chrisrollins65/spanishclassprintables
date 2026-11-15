<?php

return [
    /*
     * Node runs the builder's card code at publish time (see
     * resources/scripts/build-cards.cjs). Empty means "on the PATH", which is
     * what the droplet has — Ansible installs Node for Browsershot already.
     * `?:` rather than env()'s default, which only covers a missing variable,
     * not the empty one .env.example ships.
     */
    'node' => env('CARDS_NODE_BINARY') ?: 'node',
];
