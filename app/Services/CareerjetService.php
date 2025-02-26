<?php

namespace App\Services;

use App\Models\Job;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CareerjetService
{
    private $api;
    private $cacheTimeout;
    private $maxRetries = 3;
    private $affiliateId;

    public function __construct()
    {
        require_once base_path('app/Services/Careerjet_API.php');
        $this->api = new \Careerjet_API('en_GB');
        $this->cacheTimeout = config('app.cache_timeout', 300); // 5 minutes default
        $this->affiliateId = env('CAREERJET_AFFID', 'e22e722bd9a30362472924954689ec18');
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
                    'affid' => $this->affiliateId,
                    'contracttype' => $params['contracttype'] ?? '',
                    'contractperiod' => $params['contractperiod'] ?? '',
                    'salary_min' => $params['salary_min'] ?? '',
                    'salary_max' => $params['salary_max'] ?? '',
                    'date_from' => $params['date_from'] ?? '',
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

                // Store the jobs in database for analytics
                if (!empty($result->jobs)) {
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
     * Store jobs in the database using raw insert/update
     *
     * @param array $jobs
     * @return void
     */
    private function storeJobs($jobs)
    {
        $stored = 0;
        $skipped = 0;
        $errors = 0;
        $beforeCount = Job::count();

        foreach ($jobs as $index => $jobData) {
            try {
                // Debug what we're processing
                Log::debug("Processing job data", [
                    'index' => $index,
                    'title' => $jobData->title ?? 'No title',
                    'url_provided' => !empty($jobData->url)
                ]);

                // Skip jobs without URL (our primary identifier)
                if (empty($jobData->url)) {
                    Log::warning("Skipping job with no URL", [
                        'title' => $jobData->title ?? 'Unknown',
                        'company' => $jobData->company ?? 'Unknown'
                    ]);
                    $skipped++;
                    continue;
                }

                // Fix URLs with single quotes to prevent SQL issues
                $url = str_replace("'", "''", $jobData->url);

                // First, check if job already exists
                $exists = DB::table('career_jobs')
                    ->where('url', $jobData->url)
                    ->exists();

                if ($exists) {
                    // Job already exists - we'll update it
                    $this->updateExistingJob($jobData);
                    $skipped++;
                } else {
                    // Job doesn't exist - insert new record
                    $this->insertNewJob($jobData);
                    $stored++;
                }
            } catch (Exception $e) {
                // Log the full error and continue with the next job
                Log::error('Error processing job:', [
                    'index' => $index,
                    'url' => $jobData->url ?? 'Unknown',
                    'title' => $jobData->title ?? 'Unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errors++;
            }
        }

        $afterCount = Job::count();
        $netChange = $afterCount - $beforeCount;

        Log::info('Jobs storage result:', [
            'before_count' => $beforeCount,
            'after_count' => $afterCount,
            'net_change' => $netChange,
            'new_stored' => $stored,
            'skipped_existing' => $skipped,
            'errors' => $errors,
            'total_attempted' => count($jobs)
        ]);
    }

    /**
     * Update an existing job in the database
     */
    private function updateExistingJob($jobData)
    {
        try {
            // Update the existing job record
            $job = Job::where('url', $jobData->url)->first();

            if ($job) {
                $job->title = $jobData->title ?? $job->title;
                $job->description = $jobData->description ?? $job->description;
                $job->company = $jobData->company ?? $job->company;
                $job->locations = $jobData->locations ?? $job->locations;
                $job->job_date = $jobData->date ?? $job->job_date;
                $job->salary = $jobData->salary ?? $job->salary;

                // Update salary info if available
                if (isset($jobData->salary_min)) {
                    $job->salary_min = $jobData->salary_min;
                }

                if (isset($jobData->salary_max)) {
                    $job->salary_max = $jobData->salary_max;
                }

                if (isset($jobData->salary_type)) {
                    $job->salary_type = $jobData->salary_type;
                }

                if (isset($jobData->salary_currency_code)) {
                    $job->salary_currency_code = $jobData->salary_currency_code;
                }

                $job->save();

                Log::debug("Updated existing job", ['url' => $job->url]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Error updating job:', [
                'url' => $jobData->url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Insert a new job into the database
     */
    private function insertNewJob($jobData)
    {
        try {
            // Create using a model to ensure proper fillable fields
            $job = new Job();
            $job->title = $jobData->title;
            $job->description = $jobData->description ?? '';
            $job->company = $jobData->company ?? 'Unknown';
            $job->locations = $jobData->locations ?? '';
            $job->url = $jobData->url;
            $job->job_date = $jobData->date ?? now();
            $job->salary = $jobData->salary ?? null;

            // Add salary info if present
            if (isset($jobData->salary_min)) {
                $job->salary_min = $jobData->salary_min;
            }

            if (isset($jobData->salary_max)) {
                $job->salary_max = $jobData->salary_max;
            }

            if (isset($jobData->salary_type)) {
                $job->salary_type = $jobData->salary_type;
            }

            if (isset($jobData->salary_currency_code)) {
                $job->salary_currency_code = $jobData->salary_currency_code;
            }

            $job->save();

            Log::debug("Inserted new job", ['url' => $job->url]);
            return true;
        } catch (Exception $e) {
            Log::error('Error inserting job:', [
                'url' => $jobData->url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
