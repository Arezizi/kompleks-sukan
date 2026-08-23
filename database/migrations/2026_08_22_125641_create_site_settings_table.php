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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('weather_banner_active')->default(false);
            $table->text('weather_banner_message')->nullable();
            $table->string('day_use_note')->nullable();
            $table->time('closing_time')->default('18:30:00');
            $table->unsignedInteger('max_loan_minutes')->default(120);
            $table->unsignedInteger('penalty_days')->default(14);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
