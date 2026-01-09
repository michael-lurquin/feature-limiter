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
        Schema::create(config('feature-limiter.tables.features', 'fl_features'), function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // ex: storage, sites, custom_code
            $table->string('name'); // label humain
            $table->string('group')->nullable();
            $table->string('type'); // boolean|integer|storage
            $table->string('unit')->nullable(); // ex: "GB", "sites", "credits" (optionnal)
            $table->string('reset_period')->default(config('feature-limiter.defaults.reset_period', 'none')); // none|daily|monthly|yearly
            $table->text('description')->nullable();
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
        Schema::dropIfExists(config('feature-limiter.tables.features', 'fl_features'));
    }
};