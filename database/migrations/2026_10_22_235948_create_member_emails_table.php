<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A log of emails admins sent to members: who sent what, to which group, and how many people got it.
     */
    public function up(): void
    {
        Schema::create('member_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('audience', 20);
            $table->unsignedSmallInteger('ssc_batch')->nullable();
            $table->unsignedInteger('recipient_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_emails');
    }
};
