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
    private $debugMode = false;

    public function __construct()
    {
        require_once base_path('app/Services/Careerjet_API.php');
        $this->api = new \Careerjet_API('en_GB');
        $this->cacheTimeout = config('app.cache_timeout', 300); // 5 minutes default
        $this->affiliateId = env('CAREERJET_AFFID', 'e22e722bd9a30362472924954689ec18');
    }

    /**
     * Enable debug mode
     *
     * @param bool $debug
     * @return self
     */
    public function debug($debug = true)
    {
        $this->debugMode = $debug;
        return $this;
    }

    /**
     * Search for jobs with caching and error handling
     *
     * @param array $params Search parameters
     * @return array Results including jobs and pagination info
     */
    public function searchJobs(array $params)
    {
        // Extract and remove non-API parameters
        $forceStore = isset($params['force_store']) ? (bool)$params['force_store'] : false;
        unset($params['force_store']);

        $returnStorageDetails = isset($params['return_storage_details']) ? (bool)$params['return_storage_details'] : false;
        unset($params['return_storage_details']);

        // Create a cache key based on search parameters (exclude our custom params)
        $cacheKey = 'jobs_' . md5(serialize($params));

        // If force_store is true, skip the cache
        if ($forceStore) {
            return $this->performSearch($params, $forceStore, $returnStorageDetails);
        }

        // Try to get from cache first
        try {
            return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($params, $forceStore, $returnStorageDetails) {
                return $this->performSearch($params, $forceStore, $returnStorageDetails);
            });
        } catch (Exception $e) {
            Log::error('Careerjet API error', [
                'error' => $e->getMessage(),
                'params' => $params,
                'trace' => $e->getTraceAsString()
            ]);

            // If cache fails, try to fetch directly as fallback
            return $this->performSearch($params, $forceStore, $returnStorageDetails);
        }
    }

    /**
     * Perform the actual search API call with retries
     *
     * @param array $params
     * @param bool $forceStore Force store all jobs
     * @param bool $returnStorageDetails Return detailed storage stats
     * @return array
     */
    private function performSearch(array $params, $forceStore = false, $returnStorageDetails = false)
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
                    Log::warning('No jobs found in API response', [
                        'keywords' => $keywords,
                        'location' => $location,
                        'result_type' => $result->type
                    ]);

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

                // Storage details to return with response
                $storageDetails = [];

                // Store the jobs in database for analytics
                if (!empty($result->jobs)) {
                    $storageDetails = $this->storeJobs($result->jobs, $forceStore);
                    if ($this->debugMode) {
                        Log::info('Job storage results', $storageDetails);
                    }
                }

                $response = [
                    'total' => $result->hits,
                    'pages' => $result->pages,
                    'jobs' => $result->jobs,
                    'current_page' => $page,
                    'search_params' => [
                        'keywords' => $keywords,
                        'location' => $location
                    ]
                ];

                // Include storage details if requested
                if ($returnStorageDetails) {
                    $response['storage_details'] = $storageDetails;
                }

                return $response;
            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                Log::warning("API search attempt $attempt failed", [
                    'error' => $e->getMessage(),
                    'params' => $params,
                ]);

                // Wait a bit before retrying (exponential backoff)
                if ($attempt < $this->maxRetries) {
                    sleep(pow(2, $attempt - 1)); // 1, 2, 4 seconds...
                }
            }
        }

        // If we get here, all attempts failed
        Log::error('All API search attempts failed', [
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'attempts' => $attempt,
            'params' => $params
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
     * Store jobs in the database using batch processing
     *
     * @param array $jobs
     * @param bool $forceStore Force store all jobs even if they exist
     * @return array Statistics about storage operation
     */
    private function storeJobs($jobs, $forceStore = false)
    {
        $stored = 0;
        $skipped = 0;
        $updated = 0;
        $errors = 0;
        $beforeCount = Job::count();
        $jobBatch = [];
        $processedUrls = [];

        foreach ($jobs as $index => $jobData) {
            try {
                // Skip jobs without URL (our primary identifier)
                if (empty($jobData->url)) {
                    if ($this->debugMode) {
                        Log::warning("Skipping job with no URL", [
                            'title' => $jobData->title ?? 'Unknown',
                            'company' => $jobData->company ?? 'Unknown'
                        ]);
                    }
                    $skipped++;
                    continue;
                }

                // Skip duplicate URLs within the same batch
                if (in_array($jobData->url, $processedUrls)) {
                    $skipped++;
                    continue;
                }

                $processedUrls[] = $jobData->url;

                // Check if job already exists
                $exists = Job::where('url', $jobData->url)->exists();

                if ($exists && !$forceStore) {
                    // Update existing job record
                    $this->updateExistingJob($jobData);
                    $updated++;
                } else {
                    // Force delete if exists and we're forcing store
                    if ($exists && $forceStore) {
                        Job::where('url', $jobData->url)->delete();
                    }

                    // Add to batch for new jobs
                    $jobBatch[] = $this->prepareJobData($jobData);
                    $stored++;
                }
            } catch (Exception $e) {
                // Log detailed error and continue with next job
                Log::error('Error processing job:', [
                    'index' => $index,
                    'url' => $jobData->url ?? 'Unknown',
                    'title' => $jobData->title ?? 'Unknown',
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        // Batch insert all new jobs
        if (!empty($jobBatch)) {
            try {
                DB::table('career_jobs')->insert($jobBatch);
            } catch (Exception $e) {
                Log::error('Error performing batch insert:', [
                    'error' => $e->getMessage(),
                    'count' => count($jobBatch)
                ]);
                $errors += count($jobBatch);
                $stored = 0; // Reset stored count as the batch insert failed
            }
        }

        $afterCount = Job::count();
        $netChange = $afterCount - $beforeCount;

        return [
            'before_count' => $beforeCount,
            'after_count' => $afterCount,
            'net_change' => $netChange,
            'new_stored' => $stored,
            'updated_existing' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_attempted' => count($jobs),
            'force_store' => $forceStore
        ];
    }

    /**
     * Update an existing job in the database
     *
     * @param object $jobData
     * @return bool
     */
    private function updateExistingJob($jobData)
    {
        try {
            $updateData = [
                'title' => $jobData->title,
                'description' => $jobData->description ?? '',
                'company' => $jobData->company ?? 'Unknown',
                'locations' => $jobData->locations ?? '',
                'updated_at' => now()
            ];

            // Update salary info if available
            if (isset($jobData->salary)) {
                $updateData['salary'] = $jobData->salary;
            }

            if (isset($jobData->salary_min)) {
                $updateData['salary_min'] = $jobData->salary_min;
            }

            if (isset($jobData->salary_max)) {
                $updateData['salary_max'] = $jobData->salary_max;
            }

            if (isset($jobData->salary_type)) {
                $updateData['salary_type'] = $jobData->salary_type;
            }

            if (isset($jobData->salary_currency_code)) {
                $updateData['salary_currency_code'] = $jobData->salary_currency_code;
            }

            // Use query builder for faster updates
            DB::table('career_jobs')
                ->where('url', $jobData->url)
                ->update($updateData);

            return true;
        } catch (Exception $e) {
            Log::error('Error updating job:', [
                'url' => $jobData->url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare job data for insertion
     *
     * @param object $jobData
     * @return array
     */
    private function prepareJobData($jobData)
    {
        $now = now();
        $jobDate = isset($jobData->date) ? $jobData->date : $now->toDateString();

        // Normalize job date
        if (is_string($jobDate)) {
            try {
                $jobDate = Carbon::parse($jobDate)->toDateString();
            } catch (Exception $e) {
                $jobDate = $now->toDateString();
            }
        }

        $data = [
            'title' => $jobData->title,
            'description' => $jobData->description ?? '',
            'company' => $jobData->company ?? 'Unknown',
            'locations' => $jobData->locations ?? '',
            'url' => $jobData->url,
            'job_date' => $jobDate,
            'salary' => $jobData->salary ?? null,
            'created_at' => $now,
            'updated_at' => $now
        ];

        // Add salary info if present
        if (isset($jobData->salary_min)) {
            $data['salary_min'] = $jobData->salary_min;
        }

        if (isset($jobData->salary_max)) {
            $data['salary_max'] = $jobData->salary_max;
        }

        if (isset($jobData->salary_type)) {
            $data['salary_type'] = $jobData->salary_type;
        }

        if (isset($jobData->salary_currency_code)) {
            $data['salary_currency_code'] = $jobData->salary_currency_code;
        }

        return $data;
    }
}
