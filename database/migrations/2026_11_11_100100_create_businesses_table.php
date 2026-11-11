<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 40)->default('other');
            $table->string('tagline')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('timezone', 64)->default('America/Argentina/Buenos_Aires');
            $table->char('currency', 3)->default('ARS');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(15);
            $table->unsignedSmallInteger('min_notice_minutes')->default(60);
            $table->unsignedSmallInteger('booking_horizon_days')->default(30);
            $table->unsignedSmallInteger('cancellation_notice_hours')->default(24);
            $table->unsignedTinyInteger('deposit_percent')->default(0);
            $table->unsignedSmallInteger('hold_minutes')->default(15);
            $table->string('accent_color', 7)->default('#6d28d9');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('staff')->after('business_id');
            $table->boolean('is_bookable')->default(true)->after('role');
            $table->string('title', 80)->nullable()->after('name');
            $table->index(['business_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['role', 'is_bookable', 'title']);
        });

        Schema::dropIfExists('businesses');
    }
};
