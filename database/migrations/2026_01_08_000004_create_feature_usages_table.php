<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('feature-limiter.tables.usages', 'fl_feature_usages'), function (Blueprint $table) {
            $table->id();
            $table->morphs('usable'); // usable_type + usable_id
            $table->foreignId('feature_id')->constrained(config('feature-limiter.tables.features', 'fl_features'))->cascadeOnDelete();
            $table->date('period_start'); // ex: 2026-01-01
            $table->date('period_end'); // ex: 2026-01-31
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['usable_type', 'usable_id', 'feature_id', 'period_start'], 'feature_usage_unique_period');
            $table->index(['feature_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('feature-limiter.tables.usages', 'fl_feature_usages'));
    }
};