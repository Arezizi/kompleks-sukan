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
        Schema::table('users', function (Blueprint $table) {
            $table->string('matric_no')->nullable()->unique()->after('name');
            $table->string('programme')->nullable()->after('matric_no');
            $table->string('phone')->nullable()->after('programme');
            $table->string('setup_token', 64)->nullable()->unique()->after('password');
            $table->timestamp('setup_completed_at')->nullable()->after('setup_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['matric_no', 'programme', 'phone', 'setup_token', 'setup_completed_at']);
        });
    }
};
