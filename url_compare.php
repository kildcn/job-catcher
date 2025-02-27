<?php
// url_compare.php
// Save this file and run with: php url_compare.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the API service
$api = new \App\Services\CareerjetService();

// Function to examine jobs from a specific page
function examineJobUrls($api, $page) {
    echo "========== EXAMINING PAGE $page JOBS ==========\n";

    // Fetch jobs
    $result = $api->searchJobs([
        "keywords" => "developer",
        "location" => "berlin",
        "page" => $page,
        "pagesize" => 5
    ]);

    if (empty($result['jobs'])) {
        echo "No jobs found on page $page\n";
        return;
    }

    echo "Found " . count($result['jobs']) . " jobs\n\n";

    // Examine all job URLs
    foreach ($result['jobs'] as $index => $job) {
        echo "Job #" . ($index + 1) . ":\n";
        echo "Title: " . $job->title . "\n";
        echo "URL length: " . strlen($job->url) . " characters\n";

        // Check URL format and potential issues
        $urlHasQuery = strpos($job->url, '?') !== false;
        $urlHasFragment = strpos($job->url, '#') !== false;
        $urlHasSpecialChars = preg_match('/[\x00-\x1F\x7F-\xFF]/', $job->url);
        $urlValid = filter_var($job->url, FILTER_VALIDATE_URL) !== false;

        echo "URL analysis:\n";
        echo "- Has query parameters: " . ($urlHasQuery ? "Yes" : "No") . "\n";
        echo "- Has URL fragment: " . ($urlHasFragment ? "Yes" : "No") . "\n";
        echo "- Contains special characters: " . ($urlHasSpecialChars ? "Yes" : "No") . "\n";
        echo "- Is valid URL: " . ($urlValid ? "Yes" : "No") . "\n";

        // Try direct database insertion for this job
        try {
            // Clear first
            \Illuminate\Support\Facades\DB::table('career_jobs')->delete();

            $now = now();
            \Illuminate\Support\Facades\DB::table('career_jobs')->insert([
                'title' => $job->title,
                'description' => $job->description ?? '',
                'company' => $job->company ?? 'Unknown',
                'locations' => $job->locations ?? '',
                'url' => $job->url,
                'job_date' => isset($job->date) ? $job->date : $now->toDateString(),
                'salary' => $job->salary ?? null,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            $inserted = \Illuminate\Support\Facades\DB::table('career_jobs')->count();
            echo "DB insertion: SUCCESS (count: $inserted)\n";
        } catch (\Exception $e) {
            echo "DB insertion: FAILED - " . $e->getMessage() . "\n";
        }

        echo "\n";
    }
}

// Clear database
echo "Clearing database...\n";
\Illuminate\Support\Facades\DB::table('career_jobs')->delete();
echo "Database cleared.\n\n";

// Examine jobs from pages that behave differently
examineJobUrls($api, 1);  // Page that doesn't work
examineJobUrls($api, 3);  // Page that works

echo "Comparison complete!\n";
