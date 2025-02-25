<?php

namespace App\Http\Controllers;

use App\Services\CareerjetService;
use Illuminate\Http\Request;

class JobController extends Controller
{
    private $careerjetService;

    public function __construct(CareerjetService $careerjetService)
    {
        $this->careerjetService = $careerjetService;
    }

    public function testSearch(Request $request)
    {
        $keywords = $request->get('keywords', '');
        $location = $request->get('location', 'London');
        $page = $request->get('page', 1);
        $salaryMin = $request->get('salary_min');
        $salaryMax = $request->get('salary_max');
        $contractType = $request->get('contract_type'); // 'permanent', 'contract', 'all'

        $searchParams = [
            'keywords' => $keywords,
            'location' => $location,
            'page' => $page
        ];

        $result = $this->careerjetService->searchJobs($searchParams);

        if (!$result) {
            return response()->json([
                'error' => 'No results found or invalid location'
            ], 404);
        }

        // Filter jobs array
        if ($result['jobs']) {
            $result['jobs'] = array_filter($result['jobs'], function($job) use ($salaryMin, $salaryMax, $contractType) {
                // Skip jobs without salary type when filtering by contract type or salary
                if (($contractType || $salaryMin || $salaryMax) && !isset($job->salary_type)) {
                    return false;
                }

                // Contract type filter
                if ($contractType) {
                    if ($contractType === 'permanent' && $job->salary_type !== 'Y') return false;
                    if ($contractType === 'contract' && $job->salary_type === 'Y') return false;
                }

                // Salary filter (only for matching salary types)
                if ($salaryMin || $salaryMax) {
                    $jobMin = isset($job->salary_min) ? (float)$job->salary_min : null;
                    $jobMax = isset($job->salary_max) ? (float)$job->salary_max : $jobMin;

                    if (!$jobMin) return false;

                    // Convert everything to annual salary for comparison
                    if ($job->salary_type === 'D') {
                        $jobMin *= 220; // Approximate working days per year
                        $jobMax *= 220;
                    } elseif ($job->salary_type === 'M') {
                        $jobMin *= 12;
                        $jobMax *= 12;
                    }

                    if ($salaryMin && $jobMax < $salaryMin) return false;
                    if ($salaryMax && $jobMin > $salaryMax) return false;
                }

                return true;
            });

            // Recount filtered results
            $result['total_filtered'] = count($result['jobs']);
        }

        // Add formatted salary information
        $result['jobs'] = array_map(function($job) {
            $job->formatted_salary = $this->formatSalary($job);
            return $job;
        }, $result['jobs']);

        return response()->json([
            'data' => $result,
            'filters_applied' => [
                'keywords' => $keywords,
                'location' => $location,
                'page' => $page,
                'salary_min' => $salaryMin,
                'salary_max' => $salaryMax,
                'contract_type' => $contractType
            ]
        ]);
    }

    private function formatSalary($job)
{
    if (!isset($job->salary_type) || !isset($job->salary_min)) {
        return 'Salary not specified';
    }

    $currency = $job->salary_currency_code ?? 'GBP';
    $symbol = $currency === 'GBP' ? '£' : '€';  // Using actual symbols instead of HTML entities

    switch ($job->salary_type) {
        case 'Y':
            return sprintf('%s%s-%s per year',
                $symbol,
                number_format($job->salary_min),
                number_format($job->salary_max ?? $job->salary_min)
            );
        case 'D':
            return sprintf('%s%s-%s per day',
                $symbol,
                number_format($job->salary_min),
                number_format($job->salary_max ?? $job->salary_min)
            );
        case 'M':
            return sprintf('%s%s-%s per month',
                $symbol,
                number_format($job->salary_min),
                number_format($job->salary_max ?? $job->salary_min)
            );
        default:
            return 'Salary not specified';
    }
}
}
