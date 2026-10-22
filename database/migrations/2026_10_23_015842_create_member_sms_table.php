<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A log of SMS admins sent to members: who sent what, to which group, how many numbers and SMS parts.
     */
    public function up(): void
    {
        Schema::create('member_sms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('audience', 20);
            $table->unsignedSmallInteger('ssc_batch')->nullable();
            $table->unsignedInteger('recipient_count');
            $table->unsignedTinyInteger('parts');
            $table->string('request_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_sms');
    }
};
