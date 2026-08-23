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
        Schema::create('accessory_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suggested_equipment_type_id')->constrained('equipment_types')->cascadeOnDelete();
            $table->string('prompt');
            $table->timestamps();

            // Explicit short name: MySQL caps identifiers at 64 characters and
            // the auto-generated composite name exceeds it.
            $table->unique(['equipment_type_id', 'suggested_equipment_type_id'], 'accessory_suggestions_pair_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessory_suggestions');
    }
};
