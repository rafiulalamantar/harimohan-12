<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * admin_level gives a user the admin panel: "admin", or "super" (can also make students admins).
     * The existing admin accounts become super admins.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_level', 10)->nullable()->after('role');
        });

        DB::table('users')->where('role', 'admin')->update(['admin_level' => 'super']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_level');
        });
    }
};
