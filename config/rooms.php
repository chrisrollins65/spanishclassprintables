<?php

return [
    /*
     * Shared secret for the local packet builder's publish call. Matches the
     * ROOM_PUBLISH_SECRET set in the builder's own .env; publishing is refused
     * outright when this is empty, so an unconfigured environment fails loudly
     * rather than accepting anonymous writes.
     */
    'publish_secret' => env('ROOM_PUBLISH_SECRET', ''),
];
