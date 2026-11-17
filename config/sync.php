<?php

return [
    'enabled' => env('SYNC_ENABLED', false),
    'node_role' => env('SYNC_NODE_ROLE', 'primary_store'), // primary_store | cloud_storefront
    'node_id' => env('SYNC_NODE_ID', 'STORE-01'),
    'remote_url' => env('SYNC_REMOTE_URL', ''),
    'api_secret' => env('SYNC_API_SECRET', ''),
    'pull_interval_seconds' => (int) env('SYNC_PULL_INTERVAL_SECONDS', 30),
];
