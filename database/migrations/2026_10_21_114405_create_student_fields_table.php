<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fields', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type', 20)->default('text'); // text | number | textarea | select
            $table->json('options')->nullable(); // choices for select fields
            $table->string('help_text', 500)->nullable();
            $table->string('image')->nullable(); // reference image, e.g. a size chart
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fields');
    }
};
