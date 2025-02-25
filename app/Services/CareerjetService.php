<?php

namespace App\Services;

use App\Models\Job;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CareerjetService
{
    private $api;
    private $cacheTimeout = 300; // 5 minutes cache

    public function __construct()
    {
        require_once base_path('app/Services/Careerjet_API.php');
        $this->api = new \Careerjet_API('en_GB');
    }

    public function searchJobs(array $params)
    {
        // Create a cache key based on search parameters
        $cacheKey = 'jobs_' . md5(serialize($params));

        // Try to get from cache first
        return Cache::remember($cacheKey, $this->cacheTimeout, function () use ($params) {
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
            } catch (\Exception $e) {
                return [
                    'total' => 0,
                    'pages' => 0,
                    'jobs' => [],
                    'current_page' => $page ?? 1,
                    'search_params' => [
                        'keywords' => $keywords ?? '',
                        'location' => $location ?? ''
                    ],
                    'error' => 'Error fetching jobs: ' . $e->getMessage()
                ];
            }
        });
    }
}
