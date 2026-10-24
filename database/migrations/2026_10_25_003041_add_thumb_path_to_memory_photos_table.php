<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A card-size copy of each memory photo, so cards and slideshows don't download 2048 px originals.
     * Null until a copy exists; cards then show the original.
     */
    public function up(): void
    {
        Schema::table('memory_photos', function (Blueprint $table) {
            $table->string('thumb_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('memory_photos', function (Blueprint $table) {
            $table->dropColumn('thumb_path');
        });
    }
};
