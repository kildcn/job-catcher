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
                            {--days=30 : Number of days to keep cached data}';

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
        'remote', 'bristol', 'cambridge', 'oxford', 'uk'
    ];

    /**
     * Execute the console command.
     */
    public function handle(CareerjetService $careerjetService, AnalyticsService $analyticsService)
    {
        $this->info('Starting job analytics pre-caching');

        // Duration to keep cached data
        $cacheDays = (int) $this->option('days');

        if ($this->option('keywords') && $this->option('location')) {
            // Cache specific keyword/location
            $this->cacheAnalytics(
                $this->option('keywords'),
                $this->option('location'),
                $careerjetService,
                $analyticsService,
                $cacheDays
            );
        }
        elseif ($this->option('common-searches')) {
            // Cache common search combinations
            $this->cacheCommonSearches($careerjetService, $analyticsService, $cacheDays);
        }
        else {
            // Cache most frequently searched terms from database
            $this->cachePopularSearches($careerjetService, $analyticsService, $cacheDays);
        }

        $this->info('Pre-caching completed!');

        return Command::SUCCESS;
    }

    /**
     * Cache common search combinations
     */
    private function cacheCommonSearches($careerjetService, $analyticsService, $cacheDays)
    {
        $this->info('Pre-caching common job role and location combinations...');

        $bar = $this->output->createProgressBar(count($this->commonJobRoles) * count($this->commonLocations));
        $bar->start();

        foreach ($this->commonJobRoles as $role) {
            foreach ($this->commonLocations as $location) {
                $this->cacheAnalytics($role, $location, $careerjetService, $analyticsService, $cacheDays);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Cache popular searches based on database statistics
     */
    private function cachePopularSearches($careerjetService, $analyticsService, $cacheDays)
    {
        $this->info('Pre-caching analytics for most popular job titles...');

        // Extract most common job titles from database
        $popularTitles = Job::selectRaw('COUNT(*) as count, title')
            ->groupBy('title')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get();

        // Extract most common locations from database
        $popularLocations = Job::selectRaw('locations')
            ->distinct()
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
            $this->cacheAnalytics($title, '', $careerjetService, $analyticsService, $cacheDays);
            $bar->advance();
        }

        // Cache analytics for most common locations
        foreach ($popularLocations as $location) {
            $this->cacheAnalytics('', $location, $careerjetService, $analyticsService, $cacheDays);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Cache analytics for a specific keyword/location pair
     */
    private function cacheAnalytics($keywords, $location, $careerjetService, $analyticsService, $cacheDays)
    {
        $cacheKey = sprintf('analytics_%s_%s', md5($keywords), md5($location));

        try {
            // Update job database first
            $this->updateJobsIfNeeded($keywords, $location, $careerjetService);

            // Get matching jobs from database
            $jobs = $this->getJobsFromDatabase($keywords, $location);

            if ($jobs->isEmpty()) {
                return;
            }

            // Generate analytics
            $analytics = $analyticsService->analyzeJobs($jobs);
            $analytics['total_jobs'] = $jobs->count();
            $analytics['total_results'] = $jobs->count();
            $analytics['search_params'] = [
                'keywords' => $keywords,
                'location' => $location
            ];
            $analytics['timeline_data'] = $analyticsService->generateTimelineData($jobs);

            // Add metadata
            $analytics['meta'] = [
                'jobs_analyzed' => $jobs->count(),
                'total_available' => $jobs->count(),
                'analysis_date' => now()->toDateTimeString(),
                'pre_cached' => true
            ];

            // Cache the results
            Cache::put($cacheKey, $analytics, now()->addDays($cacheDays));

            Log::info('Pre-cached analytics', [
                'keywords' => $keywords,
                'location' => $location,
                'job_count' => $jobs->count()
            ]);
        }
        catch (\Exception $e) {
            Log::error('Error pre-caching analytics', [
                'keywords' => $keywords,
                'location' => $location,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update jobs database if needed
     */
    private function updateJobsIfNeeded($keywords, $location, $careerjetService)
    {
        try {
            // Get approximate count from API
            $result = $careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => 1,
                'pagesize' => 1
            ]);

            $apiCount = $result['total'] ?? 0;

            if ($apiCount > 0) {
                // Calculate how many pages to fetch
                $pageSize = 100;
                $pagesToFetch = min(ceil($apiCount / $pageSize), 10); // Max 10 pages

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

                    // Add a short delay to avoid API rate limits
                    if ($page < $pagesToFetch) {
                        usleep(500000); // 0.5 second
                    }
                }
            }
        }
        catch (\Exception $e) {
            Log::error('Error updating jobs database during pre-cache', [
                'keywords' => $keywords,
                'location' => $location,
                'error' => $e->getMessage()
            ]);
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
