<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'career_jobs';

    protected $fillable = [
        'title',
        'description',
        'company',
        'locations',
        'url',
        'salary',
        'job_date'
    ];

    protected $casts = [
        'job_date' => 'date'
    ];
}
