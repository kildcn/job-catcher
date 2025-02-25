<?php

namespace App\Http\Controllers;

use App\Services\CareerjetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    private $careerjetService;

    public function __construct(CareerjetService $careerjetService)
    {
        $this->careerjetService = $careerjetService;
    }

    /**
     * Display the homepage with search functionality
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keywords = $request->get('keywords', '');
        $location = $request->get('location', '');
        $salaryMin = $request->get('salary_min');
        $salaryMax = $request->get('salary_max');
        $contractType = $request->get('contract_type');
        $page = $request->get('page', 1);

        $results = null;
        $error = null;

        if ($keywords || $location) {
            try {
                $results = $this->careerjetService->searchJobs([
                    'keywords' => $keywords,
                    'location' => $location,
                    'page' => $page,
                    'salary_min' => $salaryMin,
                    'salary_max' => $salaryMax,
                    'contracttype' => $this->mapContractType($contractType)
                ]);

                if (!empty($results['error'])) {
                    $error = $results['error'];
                }

                // Enhance the results with better formatting
                if (!empty($results['jobs'])) {
                    $results['jobs'] = $this->enhanceJobResults($results['jobs']);
                }
            } catch (\Exception $e) {
                Log::error('Job search error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $error = 'An error occurred while searching for jobs. Please try again.';
            }
        }

        return view('home', compact('results', 'keywords', 'location', 'error'));
    }

    /**
     * Map our contract type filter to Careerjet's format
     *
     * @param string|null $contractType
     * @return string|null
     */
    private function mapContractType($contractType)
    {
        if ($contractType === 'permanent') {
            return 'p'; // Permanent in Careerjet API
        } elseif ($contractType === 'contract') {
            return 'c'; // Contract in Careerjet API
        }

        return null; // All types
    }

    /**
     * Enhance job results with additional formatting
     *
     * @param array $jobs
     * @return array
     */
    private function enhanceJobResults($jobs)
    {
        return array_map(function($job) {
            // Add relative time for job date
            $jobDate = \Carbon\Carbon::parse($job->date);
            $job->relative_date = $jobDate->diffForHumans();

            // Format description to remove excessive whitespace and limit length
            if (isset($job->description)) {
                $description = strip_tags($job->description);
                $description = preg_replace('/\s+/', ' ', $description);
                $job->short_description = strlen($description) > 200
                    ? substr($description, 0, 200) . '...'
                    : $description;
            }

            // Format salary if available
            if (isset($job->salary)) {
                $job->formatted_salary = $this->formatSalary($job);
            }

            return $job;
        }, $jobs);
    }

    /**
     * Format salary information
     *
     * @param object $job
     * @return string
     */
    private function formatSalary($job)
    {
        if (!isset($job->salary_type) || !isset($job->salary_min)) {
            return $job->salary ?? 'Salary not specified';
        }

        $currency = $job->salary_currency_code ?? 'GBP';
        $symbol = $currency === 'GBP' ? '£' : '€';

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
                return $job->salary ?? 'Salary not specified';
        }
    }
}
