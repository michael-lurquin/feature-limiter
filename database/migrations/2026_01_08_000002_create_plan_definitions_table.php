<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('feature-limiter.tables.plans', 'plan_definitions'), function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // free, starter, pro
            $table->string('name'); // Free, Starter, Pro
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('feature-limiter.tables.plans', 'plan_definitions'));
    }
};