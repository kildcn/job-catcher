<?php

namespace App\Console\Commands;

use App\Services\CareerjetService;
use App\Models\Job;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class FetchAllJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:fetch-all
                        {--keywords= : Specific keywords to search for}
                        {--location= : Specific location to search in}
                        {--max-pages=100 : Maximum number of pages to fetch}
                        {--page-size=20 : Number of jobs per page}
                        {--single-page= : Fetch only a specific page number}
                        {--delay=3 : Delay between API requests in seconds}
                        {--force-store : Force store all jobs even if they exist}
                        {--clear-cache : Clear the analytics cache after fetching}
                        {--minimal-logging : Reduce logging to minimum to prevent timeouts}
                        {--debug : Show verbose debug information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches all jobs matching a specific search from the API';

    /**
     * Execute the console command.
     */
    public function handle(CareerjetService $careerjetService)
    {
        // Force unlimited execution time
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        $keywords = $this->option('keywords');
        $location = $this->option('location');
        $maxPages = (int) $this->option('max-pages');
        $pageSize = (int) $this->option('page-size');
        $delay = (int) $this->option('delay');
        $debug = (bool) $this->option('debug');
        $clearCache = (bool) $this->option('clear-cache');
        $minimalLogging = (bool) $this->option('minimal-logging');
        $singlePage = $this->option('single-page');
        $forceStore = (bool) $this->option('force-store');

        if (empty($keywords) && empty($location)) {
            $this->error('You must specify at least keywords or location');
            return Command::FAILURE;
        }

        // Enable debug mode in CareerjetService if requested
        if ($debug) {
            $careerjetService->debug(true);
        }

        // Create a checkpoint key for this search
        $checkpointKey = sprintf('fetch_checkpoint_%s_%s', md5($keywords), md5($location));

        // Process differently for single page mode
        if ($singlePage !== null) {
            $pageNum = (int) $singlePage;
            $this->info("Single page mode: fetching only page {$pageNum}");
            return $this->processSinglePage($careerjetService, $keywords, $location, $pageNum, $pageSize, $minimalLogging, $forceStore);
        }

        $this->info(sprintf(
            "Starting to fetch all jobs for '%s' in '%s'",
            $keywords ?: 'any keyword',
            $location ?: 'any location'
        ));

        try {
            // First, get the total number of results
            $initialResult = $careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => 1,
                'pagesize' => 1
            ]);

            $totalJobs = $initialResult['total'] ?? 0;
            $totalPages = min($initialResult['pages'] ?? 0, $maxPages);

            if ($totalJobs === 0) {
                $this->warn("No jobs found for this search criteria.");
                return Command::SUCCESS;
            }

            $this->info(sprintf(
                "Found %d total jobs across %d pages. Will fetch %d pages (max %d per page)",
                $totalJobs,
                $initialResult['pages'] ?? 0,
                $totalPages,
                $pageSize
            ));

            // Count existing jobs in the database
            $existingCount = $this->getExistingJobsCount($keywords, $location);
            $this->info("Currently {$existingCount} matching jobs in database");

            // Get info about completed pages from checkpoint
            $checkpoint = Cache::get($checkpointKey) ?: ['completed_pages' => []];
            $completedPages = $checkpoint['completed_pages'] ?? [];

            $this->info("Already completed " . count($completedPages) . " pages");

            // Total tracking
            $totalNewJobs = 0;
            $totalProcessed = 0;

            // Generate commands for each page
            for ($page = 1; $page <= $totalPages; $page++) {
                // Skip already completed pages unless forcing store
                if (in_array($page, $completedPages) && !$forceStore) {
                    $this->info("Skipping page {$page} (already completed)");
                    continue;
                }

                $command = "php artisan jobs:fetch-all --keywords=\"{$keywords}\" --location=\"{$location}\" --single-page={$page} --page-size={$pageSize}";
                if ($minimalLogging) {
                    $command .= " --minimal-logging";
                }
                if ($debug) {
                    $command .= " --debug";
                }
                if ($forceStore) {
                    $command .= " --force-store";
                }

                $this->info("Processing page {$page}...");
                $this->line("Running: {$command}");

                // Execute the command
                exec($command, $output, $returnVar);

                // Process output and extract stored job counts
                $jobsInPage = 0;
                $newJobsInPage = 0;

                foreach ($output as $line) {
                    $this->line("   " . $line);

                    // Find the new jobs count from the output
                    if (preg_match('/Stored (\d+) new jobs/', $line, $matches)) {
                        $newJobsInPage = (int)$matches[1];
                    }

                    // Find total jobs processed
                    if (preg_match('/Found (\d+) jobs on page/', $line, $matches)) {
                        $jobsInPage = (int)$matches[1];
                    }
                }

                $totalNewJobs += $newJobsInPage;
                $totalProcessed += $jobsInPage;

                if ($returnVar !== 0) {
                    $this->error("Error processing page {$page}, return code: {$returnVar}");
                } else {
                    $this->info("Page {$page} completed successfully with {$newJobsInPage} new jobs stored");

                    // Add to completed pages
                    if (!in_array($page, $completedPages)) {
                        $completedPages[] = $page;
                        Cache::put($checkpointKey, ['completed_pages' => $completedPages], now()->addDays(1));
                    }
                }

                // Reset output array
                $output = [];

                // Delay between pages
                if ($page < $totalPages) {
                    $this->comment("Waiting {$delay} seconds before next page...");
                    sleep($delay);
                }
            }

            // Final count from database
            $finalCount = $this->getExistingJobsCount($keywords, $location);
            $percentComplete = ($totalJobs > 0) ? round(($finalCount / $totalJobs) * 100, 2) : 0;

            $this->info(sprintf(
                "Completed fetching jobs for '%s' in '%s'",
                $keywords ?: 'any keyword',
                $location ?: 'any location'
            ));
            $this->info(sprintf(
                "Total jobs processed: %d, New jobs stored: %d",
                $totalProcessed,
                $totalNewJobs
            ));
            $this->info(sprintf(
                "Database now has %d/%d jobs (%.2f%% complete)",
                $finalCount,
                $totalJobs,
                $percentComplete
            ));

            // Clear analytics cache if requested
            if ($clearCache) {
                $cacheKey = sprintf('analytics_%s_%s', md5($keywords), md5($location));
                Cache::forget($cacheKey);
                Cache::forget($cacheKey . '_full');
                $this->info("Cleared analytics cache for this search");
            }

            // Clear the checkpoint if all pages are completed
            if (count($completedPages) >= $totalPages) {
                Cache::forget($checkpointKey);
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Fatal error: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Process a single page (used for subprocess calls)
     */
    private function processSinglePage(CareerjetService $careerjetService, $keywords, $location, $page, $pageSize, $minimalLogging, $forceStore = false)
    {
        try {
            // Reduce logging during this operation if requested
            if ($minimalLogging) {
                $originalLogLevel = config('logging.channels.stack.level');
                config(['logging.channels.stack.level' => 'error']);
            }

            $this->info("Fetching page {$page}...");

            // Set network timeout
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', 30);

            // Use the API to fetch jobs with storage details
            $pageResult = $careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => $page,
                'pagesize' => $pageSize,
                'force_store' => $forceStore,
                'return_storage_details' => true
            ]);

            // Reset timeout
            ini_set('default_socket_timeout', $originalTimeout);

            // Restore log level
            if ($minimalLogging && isset($originalLogLevel)) {
                config(['logging.channels.stack.level' => $originalLogLevel]);
            }

            if (empty($pageResult['jobs'])) {
                $this->warn("No jobs returned for page {$page}");
                return Command::SUCCESS;
            }

            $jobsCount = count($pageResult['jobs']) ?? 0;
            $this->info("Found {$jobsCount} jobs on page {$page}");

            // Get storage statistics directly from the API response
            $storageDetails = $pageResult['storage_details'] ?? [
                'new_stored' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0
            ];

            $storedJobs = $storageDetails['new_stored'] ?? 0;
            $this->info("Stored {$storedJobs} new jobs on page {$page}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error processing page {$page}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Get count of existing jobs in database matching criteria
     */
    private function getExistingJobsCount($keywords, $location)
    {
        return Job::when($keywords, function ($query) use ($keywords) {
                $keywordArray = explode(' ', $keywords);
                return $query->where(function ($q) use ($keywordArray) {
                    foreach ($keywordArray as $keyword) {
                        if (strlen(trim($keyword)) > 2) {
                            $q->where(function ($inner) use ($keyword) {
                                $inner->where('title', 'like', "%{$keyword}%")
                                    ->orWhere('description', 'like', "%{$keyword}%");
                            });
                        }
                    }
                });
            })
            ->when($location, function ($query) use ($location) {
                return $query->where('locations', 'like', "%{$location}%");
            })
            ->count();
    }
}
