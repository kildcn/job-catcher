<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\CareerjetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiagnosticsController extends Controller
{
    private $careerjetService;

    public function __construct(CareerjetService $careerjetService)
    {
        $this->careerjetService = $careerjetService;
    }

    /**
     * Display job fetching diagnostics
     */
    public function jobs(Request $request)
    {
        // Increase execution time limit for this request only
        set_time_limit(180); // 3 minutes

        $keywords = $request->get('keywords', 'developer');
        $location = $request->get('location', 'berlin');
        $testFetch = $request->has('fetch');

        // Get database stats
        $dbJobs = Job::when($keywords, function ($query) use ($keywords) {
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
            ->paginate(20);

        // Get API stats
        $apiResponse = $this->careerjetService->searchJobs([
            'keywords' => $keywords,
            'location' => $location,
            'page' => 1,
            'pagesize' => 1
        ]);

        // Test job fetching if requested
        $fetchResults = null;
        if ($testFetch) {
            // Get the count before we start
            $dbCountBefore = Job::when($keywords, function ($query) use ($keywords) {
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

            // More efficient test - fetch just 1 page with 20 jobs
            $pageSize = 20;
            $pagesToFetch = 1;

            $fetchResults = [
                'pages_attempted' => $pagesToFetch,
                'jobs_fetched' => 0,
                'pages_succeeded' => 0,
                'time_started' => now()->toDateTimeString(),
                'details' => [],
                'db_count_before' => $dbCountBefore
            ];

            // Fetch jobs
            $startTime = microtime(true);
            $pageResult = $this->careerjetService->searchJobs([
              'keywords' => $keywords,
              'location' => $location,
              'page' => 1,
              'pagesize' => $pageSize,
              'force_store' => true  // Add this line
          ]);
            $endTime = microtime(true);

            $jobCount = isset($pageResult['jobs']) ? count($pageResult['jobs']) : 0;
            $success = isset($pageResult['jobs']) && !empty($pageResult['jobs']);

            $fetchResults['details'][] = [
                'page' => 1,
                'jobs_returned' => $jobCount,
                'time_taken' => round($endTime - $startTime, 2) . ' seconds',
                'success' => $success
            ];

            if ($success) {
                $fetchResults['jobs_fetched'] += $jobCount;
                $fetchResults['pages_succeeded']++;
            }

            $fetchResults['time_ended'] = now()->toDateTimeString();
            $fetchResults['total_jobs_after'] = Job::when($keywords, function ($query) use ($keywords) {
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

            $fetchResults['db_count_difference'] = $fetchResults['total_jobs_after'] - $dbCountBefore;
        }

        $diagnostics = [
            'search_terms' => [
                'keywords' => $keywords,
                'location' => $location
            ],
            'database_stats' => [
                'matching_jobs_count' => $dbJobs->total(),
                'page_count' => $dbJobs->lastPage(),
                'current_page' => $dbJobs->currentPage(),
                'per_page' => $dbJobs->perPage()
            ],
            'api_stats' => [
                'total_jobs' => $apiResponse['total'] ?? 0,
                'total_pages' => $apiResponse['pages'] ?? 0,
                'jobs_this_page' => isset($apiResponse['jobs']) ? count($apiResponse['jobs']) : 0
            ],
            'sample_jobs' => $dbJobs->take(5)->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'company' => $job->company,
                    'location' => $job->locations,
                    'date' => $job->job_date,
                    'has_salary' => !empty($job->salary_min)
                ];
            }),
            'fetch_test_results' => $fetchResults
        ];

        return view('diagnostics.jobs', [
            'diagnostics' => $diagnostics,
            'keywords' => $keywords,
            'location' => $location
        ]);
    }
}
