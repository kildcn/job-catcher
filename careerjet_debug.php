<?php
// careerjet_debug.php
// Save this file and run with: php careerjet_debug.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the CareerjetService instance
$service = new \App\Services\CareerjetService();

// Create a subclass to expose protected methods
class DebugCareerjetService extends \App\Services\CareerjetService {
    public function debugStoreJobs($jobs, $forceStore = false) {
        return $this->storeJobs($jobs, $forceStore);
    }
}

$debugService = new DebugCareerjetService();

// Function to examine a job in detail
function examineJob($job) {
    echo "Job examination:\n";
    echo "- Title: " . $job->title . "\n";
    echo "- URL length: " . strlen($job->url) . " chars\n";
    echo "- URL sample: " . substr($job->url, 0, 50) . "...\n";

    // Check for special characters in URL
    if (preg_match('/[\x00-\x1F\x7F-\xFF]/', $job->url)) {
        echo "- URL contains non-printable characters!\n";
    }

    // Check for NULL values
    foreach ((array)$job as $key => $value) {
        if ($value === null) {
            echo "- Property '$key' is NULL\n";
        }
    }
}

// Process a page and analyze storage
function processAndAnalyzePage($service, $debugService, $page) {
    echo "\n===== ANALYZING PAGE $page =====\n";

    // Get the jobs
    $result = $service->searchJobs([
        "keywords" => "developer",
        "location" => "berlin",
        "page" => $page,
        "pagesize" => 5
    ]);

    if (empty($result['jobs'])) {
        echo "No jobs found on this page.\n";
        return;
    }

    echo "Found " . count($result['jobs']) . " jobs\n";

    // Examine the first job
    echo "\nExamining first job:\n";
    examineJob($result['jobs'][0]);

    // Try to store the jobs with debug info
    echo "\nAttempting to store jobs with detailed debug...\n";

    // Clear DB first for this test
    \Illuminate\Support\Facades\DB::table('career_jobs')->delete();

    // Use the actual storeJobs method directly
    $storageDetails = $debugService->debugStoreJobs($result['jobs'], true);

    echo "Storage results:\n";
    foreach ($storageDetails as $key => $value) {
        echo "- $key: $value\n";
    }

    // Count what actually made it to the database
    $count = \Illuminate\Support\Facades\DB::table('career_jobs')->count();
    echo "Final DB count: $count jobs\n";

    if ($count === 0 && !empty($result['jobs'])) {
        // Something's wrong - check the first job again and try direct insertion
        echo "\nTrying direct database insertion for comparison...\n";

        try {
            $job = $result['jobs'][0];
            $now = now();

            \Illuminate\Support\Facades\DB::table('career_jobs')->insert([
                'title' => $job->title ?? 'Unknown Title',
                'description' => $job->description ?? '',
                'company' => $job->company ?? 'Unknown',
                'locations' => $job->locations ?? '',
                'url' => $job->url,
                'job_date' => $job->date ?? $now->toDateString(),
                'salary' => $job->salary ?? null,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            echo "Direct insertion succeeded!\n";
        } catch (\Exception $e) {
            echo "Direct insertion failed: " . $e->getMessage() . "\n";
        }
    }
}

// Clear database for clean test
echo "Clearing database...\n";
\Illuminate\Support\Facades\DB::table('career_jobs')->delete();
echo "Database cleared.\n";

// Process pages
processAndAnalyzePage($service, $debugService, 1);  // Page that doesn't work
processAndAnalyzePage($service, $debugService, 3);  // Page that works
