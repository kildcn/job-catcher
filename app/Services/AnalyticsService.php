<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;

class AnalyticsService
{
    /**
     * Analyze job data for analytics dashboard
     *
     * @param Collection $jobs
     * @return array
     */
    public function analyzeJobs($jobs)
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
            $isContract = $job->isContractRole();
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

    /**
     * Generate timeline data for charts
     *
     * @param Collection $jobs
     * @return array
     */
    public function generateTimelineData($jobs)
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
                $salary = $job->getAnnualizedSalary();
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

    /**
     * Find salary information for job
     *
     * @param \App\Models\Job $job
     * @return array|null
     */
    public function findSalaryInformation($job)
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

    /**
     * Extract number from salary text
     *
     * @param string $amount
     * @return int
     */
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

    /**
     * Extract tech skills from job description
     *
     * @param string $text
     * @return array
     */
    private function extractSkills($text)
    {
        $skills = [
            'java', 'python', 'javascript', 'typescript', 'php', 'ruby', 'golang', 'kotlin', 'swift',
            'react', 'angular', 'vue', 'node', 'express', 'django', 'flask', 'laravel', 'symfony',
            'sql', 'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch',
            'aws', 'azure', 'gcp', 'docker', 'kubernetes', 'terraform', 'jenkins', 'ci/cd',
            'agile', 'scrum', 'kanban', 'jira', 'git', 'github', 'gitlab',
            'html', 'css', 'sass', 'less', 'tailwind', 'bootstrap',
            'machine learning', 'artificial intelligence', 'ai', 'ml', 'data science',
            'devops', 'system design', 'microservices', 'api'
        ];

        $foundSkills = [];
        $lowercaseText = strtolower($text);

        foreach ($skills as $skill) {
            if (stripos($lowercaseText, $skill) !== false) {
                // Make sure it's a word boundary to avoid partial matches
                if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $lowercaseText)) {
                    $foundSkills[] = $skill;
                }
            }
        }

        return array_unique($foundSkills);
    }

    /**
     * Determine experience level from job description
     *
     * @param \App\Models\Job $job
     * @return string
     */
    private function determineExperienceLevel($job)
    {
        $text = strtolower($job->title . ' ' . ($job->description ?? ''));

        // Senior level indicators
        $seniorTerms = [
            'senior', 'lead', 'principal', 'head', 'director', 'manager', 'chief',
            'architect', 'vp', 'vice president', 'expert', 'specialist',
            'sr.', 'sr ', 'experienced', 'staff', 'advanced',
            '5+ years', '5 years', '6+ years', '7+ years', '8+ years'
        ];

        // Junior level indicators
        $juniorTerms = [
            'junior', 'graduate', 'trainee', 'entry', 'entry level', 'apprentice',
            'intern', 'assistant', 'associate', 'jr.', 'jr ',
            '0-2 years', '1-2 years', '2 years', 'no experience'
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

        // Additional experience pattern matching
        if (preg_match('/(\d+)(?:\+)?\s*(?:-\s*\d+)?\s*years?(?:\s+of)?\s+experience/i', $text, $matches)) {
            $years = intval($matches[1]);
            if ($years >= 5) {
                return 'senior';
            } elseif ($years <= 2) {
                return 'junior';
            }
        }

        // Default to mid-level if we have no other indicators
        return 'mid';
    }
}
