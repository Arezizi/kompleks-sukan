<?php

use App\Enums\FacilityCategory;
use App\Enums\FacilityStatus;
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
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('category', array_column(FacilityCategory::cases(), 'value'))->index();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->enum('status', array_column(FacilityStatus::cases(), 'value'))
                ->default(FacilityStatus::Open->value)
                ->index();
            $table->string('status_note')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
