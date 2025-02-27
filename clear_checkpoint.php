<?php
// clear_checkpoint.php
// Save this file and run with: php clear_checkpoint.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Define the specific search
$keywords = "developer";
$location = "berlin";

// Create the checkpoint key (same format as in FetchAllJobs.php)
$checkpointKey = sprintf('fetch_checkpoint_%s_%s', md5($keywords), md5($location));

// Check if the checkpoint exists
$checkpoint = \Illuminate\Support\Facades\Cache::get($checkpointKey);
echo "Before clearing:\n";
echo "Checkpoint exists: " . ($checkpoint ? "Yes" : "No") . "\n";
if ($checkpoint) {
    echo "Completed pages: " . implode(", ", $checkpoint['completed_pages'] ?? []) . "\n";
}

// Clear the checkpoint
\Illuminate\Support\Facades\Cache::forget($checkpointKey);
echo "Checkpoint cleared.\n";

// Also clear analytics cache for this search
$analyticsKey = sprintf('analytics_%s_%s', md5($keywords), md5($location));
\Illuminate\Support\Facades\Cache::forget($analyticsKey);
\Illuminate\Support\Facades\Cache::forget($analyticsKey . '_full');
echo "Analytics cache cleared.\n";

echo "\nNow you can run the fetch command again to process all pages from scratch.\n";
echo "php artisan jobs:fetch-all --keywords=\"developer\" --location=\"berlin\" --max-pages=5 --delay=1 --clear-cache\n";
