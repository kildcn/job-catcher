<?php

namespace App\Services;

use App\Models\Job;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CareerjetService
{
    private $api;
    private $cacheTimeout;
    private $maxRetries = 3;

    public function __construct()
    {
        require_once base_path('app/Services/Careerjet_API.php');
        $this->api = new \Careerjet_API('en_GB');
        $this->cacheTimeout = config('app.cache_timeout', 300); // 5 minutes default
    }

    /**
     * Search for jobs with caching and error handling
     *
     * @param array $params Search parameters
     * @return array Results including jobs and pagination info
     */
    public function searchJobs(array $params)
    {
        // Create a cache key based on search parameters
        $cacheKey = 'jobs_' . md5(serialize($params));

        // Try to get from cache first
        try {
            return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($params) {
                return $this->performSearch($params);
            });
        } catch (Exception $e) {
            Log::error('Careerjet API error', [
                'error' => $e->getMessage(),
                'params' => $params,
                'trace' => $e->getTraceAsString()
            ]);

            // If cache fails, try to fetch directly as fallback
            return $this->performSearch($params);
        }
    }

    /**
     * Perform the actual search API call with retries
     *
     * @param array $params
     * @return array
     */
    private function performSearch(array $params)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                set_time_limit(60); // Extend timeout for API call

                // Clean and validate parameters
                $keywords = trim($params['keywords'] ?? '');
                $location = trim($params['location'] ?? '');
                $page = max(1, intval($params['page'] ?? 1));
                $pagesize = max(20, min(100, intval($params['pagesize'] ?? 20))); // Between 20 and 100

                $result = $this->api->search([
                    'keywords' => $keywords,
                    'location' => $location,
                    'page' => $page,
                    'pagesize' => $pagesize,
                    'affid' => '678bdee048',
                    'contracttype' => $params['contracttype'] ?? '',
                    'contractperiod' => $params['contractperiod'] ?? '',
                    'salary_min' => $params['salary_min'] ?? '',
                    'salary_max' => $params['salary_max'] ?? '',
                ]);

                if ($result->type !== 'JOBS') {
                    return [
                        'total' => 0,
                        'pages' => 0,
                        'jobs' => [],
                        'current_page' => $page,
                        'search_params' => [
                            'keywords' => $keywords,
                            'location' => $location
                        ],
                        'error' => 'No results found'
                    ];
                }

                // Store the jobs in database for analytics if it's the first page
                if ($page === 1 && !empty($result->jobs)) {
                    $this->storeJobs($result->jobs);
                }

                return [
                    'total' => $result->hits,
                    'pages' => $result->pages,
                    'jobs' => $result->jobs,
                    'current_page' => $page,
                    'search_params' => [
                        'keywords' => $keywords,
                        'location' => $location
                    ]
                ];
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                // Wait a bit before retrying (exponential backoff)
                if ($attempt < $this->maxRetries) {
                    sleep(pow(2, $attempt - 1)); // 1, 2, 4 seconds...
                }
            }
        }

        // If we get here, all attempts failed
        Log::error('All API search attempts failed', [
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'attempts' => $attempt
        ]);

        return [
            'total' => 0,
            'pages' => 0,
            'jobs' => [],
            'current_page' => $params['page'] ?? 1,
            'search_params' => [
                'keywords' => $params['keywords'] ?? '',
                'location' => $params['location'] ?? ''
            ],
            'error' => 'Error fetching jobs. Please try again later.'
        ];
    }

    /**
     * Store jobs in the database for analytics
     *
     * @param array $jobs
     * @return void
     */
    private function storeJobs($jobs)
    {
        $stored = 0;
        $errors = 0;

        foreach ($jobs as $jobData) {
            try {
                // Create the job data array with all required fields
                $job = [
                    'title' => $jobData->title,
                    'description' => $jobData->description,
                    'company' => $jobData->company,
                    'locations' => $jobData->locations,
                    'job_date' => $jobData->date,
                    'salary' => $jobData->formatted_salary ?? $jobData->salary ?? null,
                    'salary_min' => $jobData->salary_min ?? null,
                    'salary_max' => $jobData->salary_max ?? null,
                    'salary_type' => $jobData->salary_type ?? null,
                    'salary_currency_code' => $jobData->salary_currency_code ?? null
                ];

                Job::updateOrCreate(
                    ['url' => $jobData->url],
                    $job
                );

                $stored++;
            } catch (Exception $e) {
                Log::error('Error storing job:', [
                    'url' => $jobData->url,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        Log::debug('Jobs storage result:', [
            'stored' => $stored,
            'errors' => $errors,
            'total_attempted' => count($jobs)
        ]);
    }
}
