<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_jobs', function (Blueprint $table) {
            // Add missing columns that are being used in the analytics
            if (!Schema::hasColumn('career_jobs', 'salary_min')) {
                $table->decimal('salary_min', 10, 2)->nullable();
            }

            if (!Schema::hasColumn('career_jobs', 'salary_max')) {
                $table->decimal('salary_max', 10, 2)->nullable();
            }

            if (!Schema::hasColumn('career_jobs', 'salary_type')) {
                $table->string('salary_type', 5)->nullable()->comment('Y: Yearly, M: Monthly, D: Daily, H: Hourly');
            }

            if (!Schema::hasColumn('career_jobs', 'salary_currency_code')) {
                $table->string('salary_currency_code', 5)->nullable();
            }

            // Add indexes for better performance
            $table->index(['title', 'description', 'company']);
            $table->index('job_date');
            $table->index('locations');
        });
    }

    public function down(): void
    {
        Schema::table('career_jobs', function (Blueprint $table) {
            // Drop added columns
            $table->dropColumn([
                'salary_min',
                'salary_max',
                'salary_type',
                'salary_currency_code'
            ]);

            // Drop added indexes
            $table->dropIndex(['title', 'description', 'company']);
            $table->dropIndex(['job_date']);
            $table->dropIndex(['locations']);
        });
    }
};
