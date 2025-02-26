<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'career_jobs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'company',
        'locations',
        'url',
        'salary',
        'job_date',
        'salary_min',
        'salary_max',
        'salary_type',
        'salary_currency_code'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'job_date' => 'datetime',
        'salary_min' => 'float',
        'salary_max' => 'float'
    ];

    /**
     * Determine if this is a contract role
     *
     * @return bool
     */
    public function isContractRole()
    {
        $contractTerms = ['contract', 'freelance', 'contractor', 'interim', 'temporary', 'per day', 'daily rate'];
        $titleAndDesc = strtolower($this->title . ' ' . ($this->description ?? ''));

        foreach ($contractTerms as $term) {
            if (stripos($titleAndDesc, $term) !== false) {
                return true;
            }
        }

        return stripos($this->salary ?? '', 'day') !== false ||
               stripos($this->salary ?? '', 'daily') !== false ||
               $this->salary_type === 'D';
    }

    /**
     * Get the annualized salary for comparison purposes
     *
     * @return float
     */
    public function getAnnualizedSalary()
    {
        if (!$this->salary_min) {
            return 0;
        }

        $salary = $this->salary_min;

        switch ($this->salary_type) {
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
        if ($this->salary_currency_code === 'EUR') {
            $salary *= 0.85; // Approximate GBP conversion
        }

        return $salary;
    }
}
