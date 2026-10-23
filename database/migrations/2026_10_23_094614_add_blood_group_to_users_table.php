<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional blood group (A+, O-, ...) so alumni can find donors in an emergency.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('blood_group', 3)->nullable()->after('ssc_batch');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('blood_group');
        });
    }
};
