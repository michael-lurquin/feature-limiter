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
        Schema::create(config('feature-limiter.tables.plan_feature', 'fl_feature_plan'), function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained(config('feature-limiter.tables.plans', 'fl_plans'))->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained(config('feature-limiter.tables.features', 'fl_features'))->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->boolean('is_unlimited')->default(false);

            $table->unique(['plan_id', 'feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('feature-limiter.tables.plan_feature', 'fl_feature_plan'));
    }
};