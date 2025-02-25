<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('career_jobs', function (Blueprint $table) {
            $table->text('url')->change();
            $table->text('description')->change();
            $table->text('title')->change();
            $table->text('locations')->change();
            $table->text('salary')->change();
            $table->text('company')->change();
        });
    }

    public function down()
    {
        Schema::table('career_jobs', function (Blueprint $table) {
            $table->string('url')->change();
            $table->string('description')->change();
            $table->string('title')->change();
            $table->string('locations')->change();
            $table->string('salary')->change();
            $table->string('company')->change();
        });
    }
};
