<?php

namespace App\Console\Commands;

use App\Services\CareerjetService;
use App\Models\Job;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
                        {--page-size=100 : Number of jobs per page}
                        {--delay=1 : Delay between API requests in seconds}
                        {--clear-cache : Clear the analytics cache after fetching}
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
        $keywords = $this->option('keywords');
        $location = $this->option('location');
        $maxPages = (int) $this->option('max-pages');
        $pageSize = (int) $this->option('page-size');
        $delay = (int) $this->option('delay');
        $debug = (bool) $this->option('debug');
        $clearCache = (bool) $this->option('clear-cache');

        if (empty($keywords) && empty($location)) {
            $this->error('You must specify at least keywords or location');
            return Command::FAILURE;
        }

        // Enable debug mode in CareerjetService if requested
        if ($debug) {
            $careerjetService->debug(true);
        }

        $this->info(sprintf(
            "Starting to fetch all jobs for '%s' in '%s'",
            $keywords ?: 'any keyword',
            $location ?: 'any location'
        ));

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

        // Create progress bar
        $progressBar = $this->output->createProgressBar($totalPages);
        $progressBar->start();

        $totalFetched = 0;
        $newJobs = 0;
        $errors = 0;

        // Fetch all pages
        for ($page = 1; $page <= $totalPages; $page++) {
            try {
                $this->line("");
                $this->info("Fetching page {$page} of {$totalPages}...");

                $pageResult = $careerjetService->searchJobs([
                    'keywords' => $keywords,
                    'location' => $location,
                    'page' => $page,
                    'pagesize' => $pageSize
                ]);

                if (empty($pageResult['jobs'])) {
                    $this->warn("No jobs returned for page {$page}. Stopping.");
                    break;
                }

                $jobsOnPage = count($pageResult['jobs']);
                $totalFetched += $jobsOnPage;

                // Calculate new jobs based on database count change
                $newDbCount = $this->getExistingJobsCount($keywords, $location);
                $newJobsThisPage = $newDbCount - $existingCount;
                $newJobs += $newJobsThisPage;
                $existingCount = $newDbCount;

                $this->info(sprintf(
                    "Page %d: Processed %d jobs (%d new jobs added to database)",
                    $page,
                    $jobsOnPage,
                    $newJobsThisPage
                ));

                // Delay between requests to avoid rate limiting
                if ($page < $totalPages && $delay > 0) {
                    $this->comment("Waiting {$delay} seconds before next request...");
                    sleep($delay);
                }
            } catch (\Exception $e) {
                $this->error("Error on page {$page}: " . $e->getMessage());
                Log::error("Error fetching jobs on page {$page}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'keywords' => $keywords,
                    'location' => $location,
                ]);
                $errors++;

                // If we have 3 consecutive errors, stop
                if ($errors >= 3) {
                    $this->error("Too many consecutive errors. Stopping.");
                    break;
                }

                // Continue with next page after a longer delay
                sleep($delay * 2);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Final count from database
        $finalCount = $this->getExistingJobsCount($keywords, $location);
        $percentComplete = ($totalJobs > 0) ? round(($finalCount / $totalJobs) * 100, 2) : 0;

        $this->info(sprintf(
            "Completed fetching jobs for '%s' in '%s'",
            $keywords ?: 'any keyword',
            $location ?: 'any location'
        ));
        $this->info(sprintf(
            "Total jobs processed: %d, New jobs added: %d",
            $totalFetched,
            $newJobs
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

        return Command::SUCCESS;
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
