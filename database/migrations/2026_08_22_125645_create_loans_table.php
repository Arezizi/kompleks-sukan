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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->index();
            $table->timestamp('due_at')->index();
            $table->timestamp('returned_at')->nullable()->index();
            $table->boolean('card_collected')->default(false);
            $table->boolean('card_returned')->default(false);
            $table->text('condition_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'returned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
