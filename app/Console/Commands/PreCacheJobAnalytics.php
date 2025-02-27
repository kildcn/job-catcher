<?php

namespace App\Console\Commands;

use App\Services\CareerjetService;
use App\Services\AnalyticsService;
use App\Models\Job;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PreCacheJobAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:precache
                        {--common-searches : Pre-cache common job search analytics}
                        {--keywords= : Specific keywords to cache}
                        {--location= : Specific location to cache}
                        {--days=30 : Number of days to keep cached data}
                        {--months=24 : How many months back to fetch data}
                        {--max-jobs=5000 : Maximum number of jobs to fetch per search}
                        {--force : Force refresh even if sufficient data exists}
                        {--debug : Enable debug mode with more verbose output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-caches job analytics for common searches to improve response times';

    /**
     * Common job roles for pre-caching.
     *
     * @var array
     */
    protected $commonJobRoles = [
        'developer', 'software engineer', 'web developer', 'data scientist',
        'project manager', 'product manager', 'ux designer', 'devops engineer',
        'full stack', 'frontend', 'backend', 'javascript', 'python', 'java'
    ];

    /**
     * Common locations for pre-caching.
     *
     * @var array
     */
    protected $commonLocations = [
        'london', 'manchester', 'birmingham', 'leeds', 'edinburgh', 'glasgow',
        'remote', 'bristol', 'cambridge', 'oxford', 'uk', 'berlin'
    ];

    /**
     * Execute the console command.
     */
    public function handle(CareerjetService $careerjetService, AnalyticsService $analyticsService)
    {
        $this->info('Starting job analytics pre-caching');

        // Duration to keep cached data
        $cacheDays = (int) $this->option('days');
        $maxJobs = (int) $this->option('max-jobs');
        $debug = $this->option('debug');
        $force = $this->option('force');

        if ($debug) {
            $this->info("Cache days: $cacheDays, Max jobs: $maxJobs");
            $this->info("Force refresh: " . ($force ? "Yes" : "No"));
        }

        if ($this->option('keywords') && $this->option('location')) {
            // Cache specific keyword/location
            $this->cacheAnalytics(
                $this->option('keywords'),
                $this->option('location'),
                $careerjetService,
                $analyticsService,
                $cacheDays,
                $maxJobs,
                $force,
                $debug
            );
        }
        elseif ($this->option('common-searches')) {
            // Cache common search combinations
            $this->cacheCommonSearches($careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug);
        }
        else {
            // Cache most frequently searched terms from database
            $this->cachePopularSearches($careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug);
        }

        $this->info('Pre-caching completed!');

        return Command::SUCCESS;
    }

    /**
     * Cache common search combinations
     */
    private function cacheCommonSearches($careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug)
    {
        $this->info('Pre-caching common job role and location combinations...');

        $bar = $this->output->createProgressBar(count($this->commonJobRoles) * count($this->commonLocations));
        $bar->start();

        foreach ($this->commonJobRoles as $role) {
            foreach ($this->commonLocations as $location) {
                $this->cacheAnalytics($role, $location, $careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Cache popular searches based on database statistics
     */
    private function cachePopularSearches($careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug)
    {
        $this->info('Pre-caching analytics for most popular job titles...');

        // Extract most common job titles from database
        $popularTitles = Job::selectRaw('COUNT(*) as count, title')
            ->groupBy('title')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get();

        // Extract most common locations from database
        $popularLocations = Job::selectRaw('COUNT(*) as count, locations')
            ->groupBy('locations')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->pluck('locations')
            ->toArray();

        $this->info(sprintf('Found %d unique job titles and %d locations',
            count($popularTitles),
            count($popularLocations)
        ));

        $bar = $this->output->createProgressBar(count($popularTitles) + count($popularLocations));
        $bar->start();

        // Cache analytics for most common titles
        foreach ($popularTitles as $titleData) {
            $title = $titleData->title;
            $this->cacheAnalytics($title, '', $careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug);
            $bar->advance();
        }

        // Cache analytics for most common locations
        foreach ($popularLocations as $location) {
            $this->cacheAnalytics('', $location, $careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Cache analytics for a specific keyword/location pair
     */
    private function cacheAnalytics($keywords, $location, $careerjetService, $analyticsService, $cacheDays, $maxJobs, $force, $debug)
    {
        $cacheKey = sprintf('analytics_%s_%s', md5($keywords), md5($location));

        try {
            // Update job database first
            $this->updateJobsIfNeeded($keywords, $location, $careerjetService, $maxJobs, $force, $debug);

            // Get matching jobs from database
            $jobs = $this->getJobsFromDatabase($keywords, $location);

            if ($jobs->isEmpty()) {
                $this->warn("No jobs found for '$keywords' in '$location' - skipping analytics generation");
                return;
            }

            // Generate analytics
            $analytics = $analyticsService->analyzeJobs($jobs);
            $analytics['total_jobs'] = $jobs->count();

            // Get total results count from API for reference
            $apiResultCount = $this->getTotalResultCount($keywords, $location, $careerjetService);

            $analytics['total_results'] = $apiResultCount;
            $analytics['search_params'] = [
                'keywords' => $keywords,
                'location' => $location
            ];
            $analytics['timeline_data'] = $analyticsService->generateTimelineData($jobs);

            // Add metadata
            $analytics['meta'] = [
                'jobs_analyzed' => $jobs->count(),
                'total_available' => $apiResultCount,
                'analysis_date' => now()->toDateTimeString(),
                'pre_cached' => true,
                'complete_analysis' => $jobs->count() >= $apiResultCount * 0.9
            ];

            // Cache the results
            Cache::put($cacheKey, $analytics, now()->addDays($cacheDays));

            // Also cache a full analysis version
            Cache::put($cacheKey . '_full', $analytics, now()->addDays($cacheDays));

            if ($debug) {
                $this->info(sprintf(
                    "Cached analytics for '%s' in '%s': %d/%d jobs (%.1f%%)",
                    $keywords,
                    $location,
                    $jobs->count(),
                    $apiResultCount,
                    $apiResultCount > 0 ? ($jobs->count() / $apiResultCount * 100) : 0
                ));
            }

            Log::info('Pre-cached analytics', [
                'keywords' => $keywords,
                'location' => $location,
                'job_count' => $jobs->count(),
                'api_count' => $apiResultCount
            ]);
        }
        catch (\Exception $e) {
            Log::error('Error pre-caching analytics', [
                'keywords' => $keywords,
                'location' => $location,
                'error' => $e->getMessage()
            ]);

            $this->error("Error pre-caching analytics for {$keywords} in {$location}: {$e->getMessage()}");
        }
    }

    /**
     * Get total results count from API
     */
    private function getTotalResultCount($keywords, $location, $careerjetService)
    {
        try {
            $result = $careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => 1,
                'pagesize' => 1
            ]);

            $count = $result['total'] ?? 0;
            return $count;
        } catch (\Exception $e) {
            $this->error("Error getting job count: {$e->getMessage()}");
            Log::error('Error getting job count', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Update jobs database if needed
     */
    private function updateJobsIfNeeded($keywords, $location, $careerjetService, $maxJobs, $force, $debug)
    {
        try {
            // Get approximate count from API
            $result = $careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => 1,
                'pagesize' => 1,
                'date_from' => now()->subMonths($this->option('months'))->format('Y-m-d')
            ]);

            $apiCount = $result['total'] ?? 0;

            // Check database count
            $dbCount = Job::when($keywords, function ($query) use ($keywords) {
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

            // If we have less than 90% of available jobs (up to max), fetch more
            if ($force || ($dbCount < min($apiCount, $maxJobs) * 0.9 && $apiCount > 0)) {
                // Calculate how many pages to fetch
                $pageSize = 100; // Maximum page size allowed
                $targetCount = min($apiCount, $maxJobs);
                $pagesToFetch = min(ceil($targetCount / $pageSize), 100); // Max 100 pages
                $existingCount = 0;
                $newCount = 0;

                $this->info(sprintf(
                    "Fetching jobs for '%s' in '%s': %d pages to get %d/%d jobs",
                    $keywords,
                    $location,
                    $pagesToFetch,
                    $targetCount,
                    $apiCount
                ));

                $bar = $this->output->createProgressBar($pagesToFetch);
                $bar->start();

                for ($page = 1; $page <= $pagesToFetch; $page++) {
                    $pageResult = $careerjetService->searchJobs([
                        'keywords' => $keywords,
                        'location' => $location,
                        'page' => $page,
                        'pagesize' => $pageSize
                    ]);

                    if (empty($pageResult['jobs'])) {
                        break;
                    }

                    // Count new vs existing jobs
                    foreach ($pageResult['jobs'] as $job) {
                        $exists = Job::where('url', $job->url)->exists();
                        if ($exists) {
                            $existingCount++;
                        } else {
                            $newCount++;
                        }
                    }

                    $bar->advance();

                    // Add a short delay to avoid API rate limits
                    if ($page < $pagesToFetch) {
                        usleep(500000); // 0.5 second
                    }
                }

                $bar->finish();
                $this->newLine();

                $this->info(sprintf(
                    "Done fetching: %d new jobs added, %d already existed",
                    $newCount,
                    $existingCount
                ));
            } else {
                if ($debug) {
                    $this->info(sprintf(
                        "Sufficient data for '%s' in '%s': %d/%d jobs already in database (%.1f%%)",
                        $keywords,
                        $location,
                        $dbCount,
                        $apiCount,
                        $apiCount > 0 ? ($dbCount / $apiCount * 100) : 0
                    ));
                }
            }
        }
        catch (\Exception $e) {
            Log::error('Error updating jobs database during pre-cache', [
                'keywords' => $keywords,
                'location' => $location,
                'error' => $e->getMessage()
            ]);

            $this->error("Error updating jobs database for {$keywords} in {$location}: {$e->getMessage()}");
        }
    }

    /**
     * Get jobs from database based on search criteria
     */
    private function getJobsFromDatabase($keywords, $location)
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
            ->orderBy('job_date', 'desc')
            ->get();
    }
}
