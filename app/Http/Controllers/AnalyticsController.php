<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\CareerjetService;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class AnalyticsController extends Controller
{
    private $careerjetService;
    private $analyticsService;
    private $cacheHours = 24; // Increased cache duration
    private $maxAnalysisJobs = 10000; // Maximum jobs to fetch for analysis

    public function __construct(CareerjetService $careerjetService)
    {
        $this->careerjetService = $careerjetService;
        $this->analyticsService = new AnalyticsService();
    }

    /**
     * Display the analytics dashboard
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
{
    try {
        $keywords = $request->get('keywords', '');
        $location = $request->get('location', '');

        // Generate cache key
        $cacheKey = sprintf('analytics_%s_%s', md5($keywords), md5($location));

        // Try to get from cache first
        if (Cache::has($cacheKey)) {
            $analytics = Cache::get($cacheKey);
            Log::debug('Returning cached analytics');
            return view('analytics.dashboard', compact('analytics'));
        }

        if (empty($keywords) && empty($location)) {
            return $this->getEmptyAnalytics('Please perform a search to see job market analytics.');
        }

        // Just use what's in the database - no API calls
        $jobs = $this->getJobsFromDatabase($keywords, $location);
        $jobCount = $jobs->count();

        if ($jobs->isEmpty()) {
            return $this->getEmptyAnalytics('No jobs found matching your criteria. Try a different search.');
        }

        // Analyze the jobs data
        $analytics = $this->analyticsService->analyzeJobs($jobs);
        $analytics['total_jobs'] = $jobCount;
        $analytics['total_results'] = $jobCount; // Since we can't get API counts
        $analytics['search_params'] = [
            'keywords' => $keywords,
            'location' => $location
        ];
        $analytics['timeline_data'] = $this->analyticsService->generateTimelineData($jobs);

        // Add metadata about the analysis
        $analytics['meta'] = [
            'jobs_analyzed' => $jobCount,
            'total_available' => $jobCount,
            'analysis_date' => now()->toDateTimeString(),
            'complete_analysis' => true,
            'note' => 'Analysis based on local database only'
        ];

        // Cache the results
        Cache::put($cacheKey, $analytics, now()->addHours($this->cacheHours));

        return view('analytics.dashboard', compact('analytics'));

    } catch (Exception $e) {
        Log::error('Analytics Error:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return $this->getEmptyAnalytics('An error occurred while analyzing the data: ' . $e->getMessage());
    }
}

    /**
     * Get quick count of total results from API
     *
     * @param string $keywords
     * @param string $location
     * @return int
     */
    private function getTotalResultCount($keywords, $location)
    {
        $cacheKey = 'count_' . md5($keywords . $location);

        return Cache::remember($cacheKey, now()->addHours(1), function() use ($keywords, $location) {
            try {
                $result = $this->careerjetService->searchJobs([
                    'keywords' => $keywords,
                    'location' => $location,
                    'page' => 1,
                    'pagesize' => 1 // Just need the count, not actual jobs
                ]);

                return $result['total'] ?? 0;
            } catch (Exception $e) {
                Log::error('Error getting job count', [
                    'error' => $e->getMessage()
                ]);
                return 0;
            }
        });
    }

    /**
     * Check if we need to update the job database and initiate background update if needed
     *
     * @param string $keywords
     * @param string $location
     * @param bool $fullAnalysis
     */
    private function checkAndUpdateJobData($keywords, $location, $fullAnalysis = false)
{
    // Temporarily bypass cache checks to force updates
    $updateKey = 'update_check_' . md5($keywords . $location);
    Cache::forget($updateKey); // Force update check

    // Original implementation continues...
    $dbCount = Job::when($keywords, function ($query) use ($keywords) {
        // ... existing code
    })->count();

    // Get approximate count from API
    $apiCount = $this->getTotalResultCount($keywords, $location);

    // Log the counts for debugging
    Log::info("Job counts", [
        'keywords' => $keywords,
        'location' => $location,
        'db_count' => $dbCount,
        'api_count' => $apiCount
    ]);

    // Always update for now (temporary fix)
    $targetCount = $fullAnalysis ? min($apiCount, $this->maxAnalysisJobs) : min($apiCount, 2000);
    if ($apiCount > 0) {
        $this->updateJobDatabase($keywords, $location, $targetCount);
    }
}

    /**
     * Update job database with more results from API
     *
     * @param string $keywords
     * @param string $location
     * @param int $targetCount
     */
    private function updateJobDatabase($keywords, $location, $targetCount)
    {
        Log::info('Updating job database', [
            'keywords' => $keywords,
            'location' => $location,
            'targetCount' => $targetCount
        ]);

        // Calculate how many pages we need
        $pageSize = 100;
        $pagesToFetch = min(ceil($targetCount / $pageSize), 100); // Max 100 pages

        try {
            for ($page = 1; $page <= $pagesToFetch; $page++) {
                Log::debug("Fetching jobs page {$page} of {$pagesToFetch} for database update");

                $pageResult = $this->careerjetService->searchJobs([
                    'keywords' => $keywords,
                    'location' => $location,
                    'page' => $page,
                    'pagesize' => $pageSize
                ]);

                if (empty($pageResult['jobs'])) {
                    Log::warning("Page {$page} returned no jobs, stopping pagination");
                    break;
                }

                // The CareerjetService will automatically store these jobs

                // Add a short delay to not hammer the API
                if ($page < $pagesToFetch) {
                    usleep(500000); // 0.5 second pause
                }
            }

            Log::info('Job database update completed', [
                'keywords' => $keywords,
                'location' => $location,
                'pagesFetched' => $pagesToFetch
            ]);
        } catch (Exception $e) {
            Log::error('Error updating job database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get jobs from database based on search criteria
     *
     * @param string $keywords
     * @param string $location
     * @param int|null $limit Optional limit (null for no limit)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getJobsFromDatabase($keywords, $location, $limit = null)
    {
        $query = Job::when($keywords, function ($query) use ($keywords) {
                $keywordArray = explode(' ', $keywords);
                return $query->where(function ($q) use ($keywordArray) {
                    foreach ($keywordArray as $keyword) {
                        if (strlen(trim($keyword)) > 2) { // Only search for keywords with more than 2 characters
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
            ->orderBy('job_date', 'desc');

        // Apply limit only if specified
        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get empty analytics with error message
     *
     * @param string $error
     * @return \Illuminate\View\View
     */
    private function getEmptyAnalytics($error)
    {
        return view('analytics.dashboard', [
            'analytics' => [
                'error' => $error,
                'total_jobs' => 0,
                'total_results' => 0,
                'salary_ranges' => ['permanent' => [], 'contract' => []],
                'companies' => [],
                'skills' => [],
                'experience_levels' => ['senior' => 0, 'mid' => 0, 'junior' => 0],
                'search_params' => [
                    'keywords' => request('keywords', ''),
                    'location' => request('location', '')
                ],
                'timeline_data' => []
            ]
        ]);
    }
}
