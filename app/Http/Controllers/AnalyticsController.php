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
    private $cacheHours = 24;

    public function __construct(CareerjetService $careerjetService)
    {
        $this->careerjetService = $careerjetService;
        $this->analyticsService = new AnalyticsService();
    }

    /**
     * Display the analytics dashboard
     */
    public function index(Request $request)
    {
        try {
            $keywords = $request->get('keywords', '');
            $location = $request->get('location', '');
            $fullAnalysis = $request->has('full_analysis');
            $forceRefresh = $request->has('refresh');

            // When we need to refresh, we'll fetch just one page for now and
            // show what we have in the database
            if ($forceRefresh) {
                // Just fetch one page of data to update the database
                $this->fetchOnePage($keywords, $location);
            }

            // Generate cache key
            $cacheKey = sprintf('analytics_%s_%s%s',
                md5($keywords),
                md5($location),
                $fullAnalysis ? '_full' : ''
            );

            // Try to get from cache first (unless forced refresh)
            if (!$forceRefresh && Cache::has($cacheKey)) {
                $analytics = Cache::get($cacheKey);
                Log::debug('Returning cached analytics');
                return view('analytics.dashboard', compact('analytics'));
            }

            if (empty($keywords) && empty($location)) {
                return $this->getEmptyAnalytics('Please perform a search to see job market analytics.');
            }

            // Get matching jobs from the database (not from the API)
            $jobs = $this->getJobsFromDatabase($keywords, $location);
            $jobCount = $jobs->count();

            if ($jobs->isEmpty()) {
                return $this->getEmptyAnalytics('No jobs found matching your criteria. Try a different search.');
            }

            // Get total count from API (without fetching all jobs)
            $apiCount = $this->getApiJobCount($keywords, $location);

            // Analyze the jobs data using what we have in the database
            $analytics = $this->analyticsService->analyzeJobs($jobs);
            $analytics['total_jobs'] = $jobCount;
            $analytics['total_results'] = $apiCount ?: $jobCount;
            $analytics['search_params'] = [
                'keywords' => $keywords,
                'location' => $location,
                'full_analysis' => $fullAnalysis
            ];
            $analytics['timeline_data'] = $this->analyticsService->generateTimelineData($jobs);

            // Add metadata about the analysis
            $analytics['meta'] = [
                'jobs_analyzed' => $jobCount,
                'total_available' => $apiCount ?: $jobCount,
                'analysis_date' => now()->toDateTimeString(),
                'complete_analysis' => $fullAnalysis || $jobCount >= $apiCount * 0.9,
                'note' => $fullAnalysis ? 'Comprehensive job market analysis' : 'Analysis based on currently available data'
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
     * Just fetch one page of data to update the database
     */
    private function fetchOnePage($keywords, $location)
    {
        try {
            Log::info("Fetching one page for quick refresh");

            // Get first page of jobs
            $result = $this->careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => 1,
                'pagesize' => 100 // Get as many as possible in one request
            ]);

            Log::info("Fetched quick refresh page", [
                'job_count' => isset($result['jobs']) ? count($result['jobs']) : 0
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error("Error in quick refresh", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get API job count without fetching all jobs
     */
    private function getApiJobCount($keywords, $location)
    {
        try {
            $result = $this->careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => 1,
                'pagesize' => 1
            ]);

            return $result['total'] ?? 0;
        } catch (Exception $e) {
            Log::error("Error getting API job count", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get jobs from database based on search criteria
     */
    private function getJobsFromDatabase($keywords, $location)
    {
        $query = Job::when($keywords, function ($query) use ($keywords) {
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
            ->orderBy('job_date', 'desc');

        return $query->get();
    }

    /**
     * Get empty analytics with error message
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
