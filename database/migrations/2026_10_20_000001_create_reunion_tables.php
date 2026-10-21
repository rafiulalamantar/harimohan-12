<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('reunion_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date')->nullable();
            $table->string('venue')->nullable();
            $table->decimal('fee', 10, 2);
            $table->date('payment_deadline')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reunion_event_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('invoice_no', 40)->unique();
            $table->string('method', 20)->default('bkash');
            $table->string('status', 20)->default('initiated')->index(); // initiated | completed | failed | cancelled
            $table->string('bkash_payment_id')->nullable()->unique();
            $table->string('trx_id')->nullable()->index();
            $table->string('payer_msisdn', 20)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'reunion_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('reunion_events');
        Schema::dropIfExists('announcements');
    }
};
