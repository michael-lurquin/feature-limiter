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
        Schema::create(config('feature-limiter.tables.plans', 'fl_plans'), function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // free, starter, pro
            $table->string('name'); // Free, Starter, Pro
            $table->longText('description')->nullable();
            $table->unsignedInteger('price_monthly')->nullable(); // 10.99 => 1099 => price of cents
            $table->unsignedInteger('price_yearly')->nullable();

            $table->string('provider')->nullable(); // 'cashier', 'paddle', ...
            $table->string('provider_monthly_id')->nullable(); // ex price_xxx
            $table->string('provider_yearly_id')->nullable();

            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort', 'provider', 'provider_monthly_id', 'provider_yearly_id'], 'fl_plans_active_sort_and_provider_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('feature-limiter.tables.plans', 'fl_plans'));
    }
};