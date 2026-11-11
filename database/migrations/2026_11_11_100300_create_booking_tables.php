<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'email']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->dateTime('starts_at')->comment('UTC');
            $table->dateTime('ends_at')->comment('UTC');
            $table->dateTime('blocked_until')->comment('UTC, ends_at + service buffer');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('deposit_cents')->default(0);
            $table->dateTime('hold_expires_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 40)->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'starts_at']);
            $table->index(['staff_id', 'starts_at', 'status']);
            $table->index(['status', 'hold_expires_at']);
            $table->index(['status', 'reminder_sent_at', 'starts_at']);
        });

        // MySQL has no EXCLUDE constraint (Postgres' `EXCLUDE USING gist (tstzrange WITH &&)`).
        // We discretise time into 5-minute blocks: an active appointment owns one row per block
        // it occupies, and the composite PRIMARY KEY makes an overlap physically impossible,
        // even if two transactions slip past the application-level checks at the same time.
        Schema::create('appointment_blocks', function (Blueprint $table) {
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('starts_at')->comment('UTC, aligned to 5 minutes');
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();

            $table->primary(['staff_id', 'starts_at']);
            $table->index('appointment_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('checkout_id')->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id');
            $table->string('type', 60);
            $table->json('payload');
            $table->dateTime('processed_at')->nullable();
            $table->string('result', 40)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('appointment_blocks');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('customers');
    }
};
