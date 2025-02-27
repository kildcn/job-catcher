<?php
// simple_debug.php
// Save this file in your Laravel project root and run with: php simple_debug.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the API service
$api = new \App\Services\CareerjetService();

// Clear database first
echo "Clearing database...\n";
\Illuminate\Support\Facades\DB::table('career_jobs')->delete();
echo "Database cleared.\n\n";

// Function to process a single page
function processPage($api, $page) {
    echo "==== Processing Page $page ====\n";

    $result = $api->searchJobs([
        "keywords" => "developer",
        "location" => "berlin",
        "page" => $page,
        "pagesize" => 5
    ]);

    echo "Found " . count($result['jobs']) . " jobs\n";

    if (empty($result['jobs'])) {
        echo "No jobs found on this page.\n";
        return;
    }

    // Display first job info
    $job = $result['jobs'][0];
    echo "Sample job: " . $job->title . " at " . $job->company . "\n";

    // Now store directly using the same logic as CareerjetService
    $now = now();
    $stored = 0;
    $errors = 0;

    foreach ($result['jobs'] as $index => $job) {
        try {
            // Store job using direct database insert
            \Illuminate\Support\Facades\DB::table('career_jobs')->insert([
                'title' => $job->title,
                'description' => $job->description ?? '',
                'company' => $job->company ?? 'Unknown',
                'locations' => $job->locations ?? '',
                'url' => $job->url,
                'job_date' => $job->date ?? $now->toDateString(),
                'salary' => $job->salary ?? null,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            $stored++;
        } catch (\Exception $e) {
            echo "Error storing job #$index: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "Stored $stored new jobs, encountered $errors errors\n\n";
}

// Process pages
processPage($api, 1);  // Page that doesn't work with artisan command
processPage($api, 3);  // Page that works with artisan command

// Count total jobs stored
$count = \Illuminate\Support\Facades\DB::table('career_jobs')->count();
echo "Final database count: $count jobs\n";
