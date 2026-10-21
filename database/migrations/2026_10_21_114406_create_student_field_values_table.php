<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['student_field_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_field_values');
    }
};
