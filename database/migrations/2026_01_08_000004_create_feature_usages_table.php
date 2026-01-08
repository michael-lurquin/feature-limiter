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
        Schema::create(config('feature-limiter.tables.usages', 'feature_usages'), function (Blueprint $table) {
            $table->id();
            $table->morphs('subject'); // subject_type + subject_id
            $table->foreignId('feature_id')->constrained('feature_definitions')->cascadeOnDelete();
            $table->date('period_start'); // ex: 2026-01-01
            $table->date('period_end'); // ex: 2026-01-31
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'feature_id', 'period_start'], 'feature_usage_unique_period');
            $table->index(['feature_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('feature-limiter.tables.usages', 'feature_usages'));
    }
};