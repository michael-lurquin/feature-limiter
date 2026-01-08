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
        Schema::create(config('feature-limiter.tables.plan_features', 'plan_feature_values'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plan_definitions')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('feature_definitions')->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('feature-limiter.tables.plan_features', 'plan_feature_values'));
    }
};