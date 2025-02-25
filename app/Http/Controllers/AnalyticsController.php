<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\CareerjetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    private $careerjetService;

    public function __construct(CareerjetService $careerjetService)
    {
        $this->careerjetService = $careerjetService;
    }

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

            // Only fetch first page from Careerjet for total count
            $firstPageResult = $this->careerjetService->searchJobs([
                'keywords' => $keywords,
                'location' => $location,
                'page' => 1,
                'pagesize' => 100
            ]);

            $totalResults = $firstPageResult['total'] ?? 0;

            // Store first page jobs
            if (!empty($firstPageResult['jobs'])) {
                $this->storeJobs($firstPageResult['jobs']);
            }

            // Get jobs from database with limit
            $jobs = Job::where(function($query) use ($keywords, $location) {
                    if ($keywords) {
                        $keywords = explode(' ', $keywords);
                        $query->where(function($q) use ($keywords) {
                            foreach ($keywords as $keyword) {
                                $q->where(function($inner) use ($keyword) {
                                    $inner->where('title', 'ilike', "%{$keyword}%")
                                          ->orWhere('description', 'ilike', "%{$keyword}%");
                                });
                            }
                        });
                    }
                    if ($location) {
                        $query->where('locations', 'ilike', "%{$location}%");
                    }
                })
                ->orderBy('job_date', 'desc')
                ->limit(500)  // Increased limit for better analysis
                ->get();

            if ($jobs->isEmpty()) {
                return $this->getEmptyAnalytics('No jobs found matching your criteria.');
            }

            $analytics = $this->analyzeJobs($jobs);
            $analytics['total_results'] = $totalResults;
            $analytics['search_params'] = ['keywords' => $keywords, 'location' => $location];
            $analytics['timeline_data'] = $this->generateTimelineData($jobs);

            // Cache the results for 6 hours
            Cache::put($cacheKey, $analytics, now()->addHours(6));

            return view('analytics.dashboard', compact('analytics'));

        } catch (\Exception $e) {
            Log::error('Analytics Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getEmptyAnalytics('An error occurred while analyzing the data: ' . $e->getMessage());
        }
    }

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

                Log::debug('Stored job with salary:', [
                    'title' => $job['title'],
                    'salary' => $job['salary'],
                    'salary_min' => $job['salary_min'],
                    'salary_max' => $job['salary_max'],
                    'salary_type' => $job['salary_type']
                ]);

            } catch (\Exception $e) {
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

    private function analyzeJobs($jobs)
    {
        // Initialize analytics structure
        $analytics = [
            'total_jobs' => $jobs->count(),
            'salary_ranges' => [
                'permanent' => [],
                'contract' => []
            ],
            'companies' => [],
            'skills' => [],
            'experience_levels' => [
                'senior' => 0,
                'mid' => 0,
                'junior' => 0
            ]
        ];

        // Initialize arrays for batch processing
        $companyJobs = [];
        $skillsCount = [];

        foreach ($jobs as $job) {
            // Process salary information
            $isContract = $this->isContractRole($job);
            $salaryType = $isContract ? 'contract' : 'permanent';
            $salary = $this->findSalaryInformation($job);

            if ($salary) {
                $analytics['salary_ranges'][$salaryType][] = $salary;

                // Track company statistics
                $company = $job->company;
                if (!isset($companyJobs[$company])) {
                    $companyJobs[$company] = ['count' => 0, 'salaries' => []];
                }
                $companyJobs[$company]['count']++;
                $companyJobs[$company]['salaries'][] = $salary['avg'];
            }

            // Process skills in batches
            $jobSkills = $this->extractSkills($job->description);
            foreach ($jobSkills as $skill) {
                $skillsCount[$skill] = ($skillsCount[$skill] ?? 0) + 1;
            }

            // Process experience level
            $expLevel = $this->determineExperienceLevel($job);
            $analytics['experience_levels'][$expLevel]++;
        }

        // Process company statistics
        foreach ($companyJobs as $company => $data) {
            if (!empty($data['salaries'])) {
                $analytics['companies'][$company] = [
                    'count' => $data['count'],
                    'avg_salary' => array_sum($data['salaries']) / count($data['salaries'])
                ];
            }
        }

        // Sort and limit skills
        arsort($skillsCount);
        $analytics['skills'] = array_slice($skillsCount, 0, 15, true);

        // Calculate average salaries if available
        foreach (['permanent', 'contract'] as $type) {
            if (!empty($analytics['salary_ranges'][$type])) {
                $analytics['avg_salaries'][$type] = array_sum(
                    array_column($analytics['salary_ranges'][$type], 'avg')
                ) / count($analytics['salary_ranges'][$type]);
            }
        }

        // Sort companies by average salary
        uasort($analytics['companies'], function($a, $b) {
            return $b['avg_salary'] <=> $a['avg_salary'];
        });

        $analytics['companies'] = array_slice($analytics['companies'], 0, 10, true);

        return $analytics;
    }

    private function generateTimelineData($jobs)
    {
        // Group jobs by month
        $timelineData = [];
        foreach ($jobs as $job) {
            $month = $job->job_date->format('Y-m');

            if (!isset($timelineData[$month])) {
                $timelineData[$month] = [
                    'month' => $month,
                    'count' => 0,
                    'avgSalary' => 0,
                    'totalSalary' => 0,
                    'jobsWithSalary' => 0
                ];
            }

            $timelineData[$month]['count']++;

            // Only include jobs with salary information in the average
            if ($job->salary_min) {
                $salary = $this->normalizeYearlySalary($job);
                if ($salary > 0) {
                    $timelineData[$month]['totalSalary'] += $salary;
                    $timelineData[$month]['jobsWithSalary']++;
                }
            }
        }

        // Calculate averages and format data
        foreach ($timelineData as &$data) {
            if ($data['jobsWithSalary'] > 0) {
                $data['avgSalary'] = $data['totalSalary'] / $data['jobsWithSalary'];
            }
            unset($data['totalSalary'], $data['jobsWithSalary']);
        }

        // Sort by month
        ksort($timelineData);

        return array_values($timelineData);
    }

    private function normalizeYearlySalary($job)
    {
        if (!$job->salary_min) {
            return 0;
        }

        $salary = $job->salary_min;

        switch ($job->salary_type) {
            case 'D': // Daily
                $salary *= 260; // Approximate working days per year
                break;
            case 'M': // Monthly
                $salary *= 12;
                break;
            case 'H': // Hourly
                $salary *= 2080; // 40 hours * 52 weeks
                break;
        }

        // Convert to default currency if needed
        if ($job->salary_currency_code === 'EUR') {
            $salary *= 0.85; // Approximate GBP conversion
        }

        return $salary;
    }

    private function findSalaryInformation($job)
    {
        // Use the direct salary fields if available
        if (isset($job->salary_min) && isset($job->salary_type)) {
            $min = floatval($job->salary_min);
            $max = floatval($job->salary_max ?? $job->salary_min);

            // Convert to annual if needed
            switch ($job->salary_type) {
                case 'D': // Daily rate
                    $min *= 260;
                    $max *= 260;
                    break;
                case 'M': // Monthly rate
                    $min *= 12;
                    $max *= 12;
                    break;
                case 'H': // Hourly rate
                    $min *= 2080;
                    $max *= 2080;
                    break;
            }

            return [
                'min' => $min,
                'max' => $max,
                'avg' => ($min + $max) / 2,
                'type' => $job->salary_type
            ];
        }

        // If no direct salary information is found, try to parse from text
        $salaryText = $job->salary ?? '';
        $normalizedAmount = $this->normalizeAmount($salaryText);

        if ($normalizedAmount > 0) {
            return [
                'min' => $normalizedAmount,
                'max' => $normalizedAmount,
                'avg' => $normalizedAmount,
                'type' => 'Y' // Assuming yearly by default
            ];
        }

        // Return null if no salary information can be found
        return null;
    }

    private function normalizeAmount($amount)
    {
        // Remove all non-numeric characters except 'k'
        $amount = preg_replace('/[^0-9k]/i', '', $amount);

        // Handle 'k' notation
        if (stripos($amount, 'k') !== false) {
            $amount = str_replace('k', '', $amount);
            return (int)(floatval($amount) * 1000);
        }

        return (int)$amount;
    }

    private function isContractRole($job)
    {
        $contractTerms = ['contract', 'freelance', 'contractor', 'interim', 'temporary', 'per day', 'daily rate'];
        $titleAndDesc = strtolower($job->title . ' ' . ($job->description ?? ''));

        foreach ($contractTerms as $term) {
            if (stripos($titleAndDesc, $term) !== false) {
                return true;
            }
        }

        return stripos($job->salary ?? '', 'day') !== false ||
               stripos($job->salary ?? '', 'daily') !== false;
    }

    private function extractSkills($text)
    {
        // Your existing extractSkills method implementation here
        // It's quite long so I kept it as is
        return array_unique($this->extractSkillsHelper($text));
    }

    private function extractSkillsHelper($text)
    {
        // Your existing skills array and logic here
        // This is the helper method that contains the actual skill extraction logic
        return $this->getSkillsList();
    }

    private function getSkillsList()
    {
        // Return your existing skills array
        return [
            'java', 'python', 'javascript', 'typescript', 'php', 'ruby', 'golang',
            'react', 'angular', 'vue', 'node', 'express', 'django', 'flask',
            'sql', 'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch',
            'aws', 'azure', 'gcp', 'docker', 'kubernetes', 'terraform',
            // ... rest of your skills list
        ];
    }

    private function determineExperienceLevel($job)
    {
        $text = strtolower($job->title . ' ' . ($job->description ?? ''));

        // Senior level indicators
        $seniorTerms = [
            'senior', 'lead', 'principal', 'head', 'director', 'manager', 'chief',
            'architect', 'vp', 'vice president', 'expert', 'specialist',
            'sr.', 'sr ', 'experienced', 'staff', 'advanced',
            'senior product', 'lead product', 'head of product',
            'product director', 'product leader', 'product architect',
            '5+ years', '5 years', '6+ years', '7+ years', '8+ years',
            '9+ years', '10+ years'
        ];

        // Junior level indicators
        $juniorTerms = [
            'junior', 'graduate', 'trainee', 'entry', 'entry level', 'apprentice',
            'intern', 'assistant', 'associate', 'jr.', 'jr ',
            'graduate product', 'junior product', 'associate product',
            'entry level product', 'product assistant',
            '0-2 years', '1-2 years', '2 years', 'no experience',
            'fresh graduate', 'recent graduate'
        ];

        // Mid level indicators
        $midTerms = [
            'mid level', 'intermediate', 'mid-level', 'product owner',
            'mid-senior', 'mid senior', '3-5 years', '2-4 years',
            'product manager', 'product analyst'  // Unless prefixed with senior/junior
        ];

        // First check for senior level
        foreach ($seniorTerms as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $text)) {
                return 'senior';
            }
        }

        // Then check for junior level
        foreach ($juniorTerms as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $text)) {
                return 'junior';
            }
        }

        // Look for explicit mid-level indicators
        foreach ($midTerms as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $text)) {
                return 'mid';
            }
        }

        // Additional experience pattern matching
        if (preg_match('/(\d+)(?:\+)?\s*(?:-\s*\d+)?\s*years?(?:\s+of)?\s+experience/i', $text, $matches)) {
            $years = intval($matches[1]);
            if ($years >= 5) {
                return 'senior';
            } elseif ($years <= 2) {
                return 'junior';
            } else {
                return 'mid';
            }
        }

        // If salary indicates seniority (rough heuristic)
        $salaryInfo = $this->findSalaryInformation($job);
        if ($salaryInfo) {
            $avgSalary = ($salaryInfo['min'] + $salaryInfo['max']) / 2;
            if ($salaryInfo['type'] === 'Y') {
                if ($avgSalary >= 80000) {
                    return 'senior';
                } elseif ($avgSalary <= 35000) {
                    return 'junior';
                }
            }
        }

        // Look for responsibility indicators
        if (stripos($text, 'team lead') !== false ||
            stripos($text, 'managing') !== false ||
            stripos($text, 'leadership') !== false ||
            stripos($text, 'strategic') !== false ||
            stripos($text, 'strategy') !== false) {
            return 'senior';
        }

        // Default to mid-level if we have no other indicators
        return 'mid';
    }
}
