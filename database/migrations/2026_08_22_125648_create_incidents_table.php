<?php

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', array_column(IncidentType::cases(), 'value'))
                ->default(IncidentType::Other->value)
                ->index();
            $table->enum('severity', array_column(IncidentSeverity::cases(), 'value'))
                ->default(IncidentSeverity::Low->value);
            $table->string('title');
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
