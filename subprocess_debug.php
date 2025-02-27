<?php
// subprocess_debug.php
// Save this file and run with: php subprocess_debug.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Echo the commands that would be run for each page
function echoPotentialCommand($page, $forceStore = false) {
    $keywords = "developer";
    $location = "berlin";
    $pageSize = 20;

    $command = "php artisan jobs:fetch-all --keywords=\"{$keywords}\" --location=\"{$location}\" --single-page={$page} --page-size={$pageSize}";

    // Add flags
    if ($forceStore) {
        $command .= " --force-store";
    }

    echo "Command for page {$page}: {$command}\n";

    // Now run it and capture output
    echo "Executing...\n";
    exec($command, $output, $returnCode);

    echo "Return code: {$returnCode}\n";
    echo "Output summary: " . count($output) . " lines\n";

    // Display key output lines
    foreach ($output as $line) {
        if (strpos($line, "Stored") !== false || strpos($line, "Found") !== false) {
            echo "  > {$line}\n";
        }
    }
    echo "\n";
}

// Clear database first
echo "Clearing database for clean test...\n";
\Illuminate\Support\Facades\DB::table('career_jobs')->delete();
echo "Database cleared.\n\n";

// Test pages with and without force-store
echo "===== WITHOUT FORCE STORE =====\n";
echoPotentialCommand(1); // Page 1 without force-store
echoPotentialCommand(3); // Page 3 without force-store

echo "===== WITH FORCE STORE =====\n";
echoPotentialCommand(1, true); // Page 1 with force-store
echoPotentialCommand(3, true); // Page 3 with force-store

// Check final database state
$count = \Illuminate\Support\Facades\DB::table('career_jobs')->count();
echo "Final database count: {$count} jobs\n";
