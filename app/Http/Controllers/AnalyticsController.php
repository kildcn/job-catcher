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
    private $cacheHours = 6;

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

        // First page to get total count and initial jobs
        $firstPageResult = $this->careerjetService->searchJobs([
            'keywords' => $keywords,
            'location' => $location,
            'page' => 1,
            'pagesize' => 100 // Increased from 20 to 100 for better initial data
        ]);

        $totalResults = $firstPageResult['total'] ?? 0;

        // Calculate how many additional pages we need to fetch (up to 5 total pages)
        $maxPages = 5; // Fetch up to 5 pages (500 jobs max)
        $totalPages = min($maxPages, ceil($totalResults / 100));

        Log::info('Starting analytics job fetch', [
            'keywords' => $keywords,
            'location' => $location,
            'totalResults' => $totalResults,
            'pagesToFetch' => $totalPages
        ]);

        // Fetch additional pages for better analysis
        $fetchedPages = 1; // We already fetched page 1
        if ($totalPages > 1) {
            for ($page = 2; $page <= $totalPages; $page++) {
                try {
                    Log::debug("Fetching jobs page {$page} of {$totalPages}");

                    $pageResult = $this->careerjetService->searchJobs([
                        'keywords' => $keywords,
                        'location' => $location,
                        'page' => $page,
                        'pagesize' => 100
                    ]);

                    $fetchedPages++;

                    if (empty($pageResult['jobs'])) {
                        Log::warning("Page {$page} returned no jobs, stopping pagination");
                        break;
                    }

                    // The CareerjetService will automatically store these jobs
                } catch (Exception $e) {
                    Log::error("Error fetching page {$page}", [
                        'error' => $e->getMessage()
                    ]);
                    // Continue with what we have so far rather than failing completely
                    break;
                }
            }
        }

        // Get jobs from database with increased limit - we should now have more data
        $jobs = $this->getJobsFromDatabase($keywords, $location, 500);
        $jobCount = $jobs->count();

        Log::info('Analytics data retrieved', [
            'jobsAnalyzed' => $jobCount,
            'totalAvailable' => $totalResults,
            'pagesProcessed' => $fetchedPages
        ]);

        if ($jobs->isEmpty()) {
            return $this->getEmptyAnalytics('No jobs found matching your criteria.');
        }

        // Analyze the jobs data
        $analytics = $this->analyticsService->analyzeJobs($jobs);
        $analytics['total_results'] = $totalResults;
        $analytics['search_params'] = [
            'keywords' => $keywords,
            'location' => $location
        ];
        $analytics['timeline_data'] = $this->analyticsService->generateTimelineData($jobs);

        // Add metadata about the analysis
        $analytics['meta'] = [
            'jobs_analyzed' => $jobCount,
            'total_available' => $totalResults,
            'pages_processed' => $fetchedPages,
            'analysis_date' => now()->toDateTimeString()
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
 * Get jobs from database based on search criteria
 *
 * @param string $keywords
 * @param string $location
 * @param int $limit
 * @return \Illuminate\Database\Eloquent\Collection
 */
private function getJobsFromDatabase($keywords, $location, $limit = 500)
{
    return Job::when($keywords, function ($query) use ($keywords) {
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
        ->orderBy('job_date', 'desc')
        ->limit($limit)
        ->get();
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
