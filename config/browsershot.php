<?php

return [
    /*
     * Where the renderer's parts are. Empty means "on the PATH", which is what
     * the droplet has after Ansible installs Chrome; a Windows development
     * machine points at the packet builder's own bundled Chrome so nothing has
     * to be installed twice.
     */
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH', ''),
    'node_binary' => env('BROWSERSHOT_NODE_BINARY', ''),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY', ''),

    // Chrome's own sandbox needs kernel namespaces a small VPS may not grant.
    'no_sandbox' => (bool) env('BROWSERSHOT_NO_SANDBOX', false),
];
