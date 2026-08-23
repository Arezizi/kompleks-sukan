<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('token')->nullable()->unique()->after('id');
        });

        // Give existing loans a token so every loan can render its return QR.
        DB::table('loans')->whereNull('token')->orderBy('id')->each(function (stdClass $loan): void {
            DB::table('loans')
                ->where('id', $loan->id)
                ->update(['token' => Str::random(40)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
