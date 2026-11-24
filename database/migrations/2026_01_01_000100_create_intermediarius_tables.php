<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 32);
            $table->string('type', 64);
            $table->unsignedBigInteger('price');
            $table->string('province');
            $table->string('municipality');
            $table->string('neighborhood');
            $table->unsignedInteger('bedrooms')->default(0);
            $table->unsignedInteger('baths')->default(0);
            $table->unsignedInteger('area')->nullable();
            $table->string('private_address');
            $table->decimal('private_lat', 10, 7)->nullable();
            $table->decimal('private_lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('visual_pin_x');
            $table->unsignedTinyInteger('visual_pin_y');
            $table->text('description');
            $table->json('amenities')->nullable();
            $table->string('status', 32);
            $table->text('rejection_reason')->nullable();
            $table->string('address_fingerprint')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category', 'province']);
            $table->index(['municipality', 'status']);
        });

        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });

        Schema::create('agent_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32);
            $table->decimal('commission_pct', 5, 2);
            $table->text('terms')->nullable();
            $table->string('tracking_code', 16)->unique();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'agent_id']);
            $table->index(['property_id', 'status']);
        });

        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('days');
            $table->string('start', 5);
            $table->string('end', 5);
            $table->unsignedSmallInteger('slot');
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'property_id']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('status', 32);
            $table->string('code', 16)->nullable()->unique();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'scheduled_at', 'status']);
            $table->index(['agent_id', 'scheduled_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('purpose', 32);
            $table->string('gateway', 32);
            $table->string('method', 32);
            $table->string('reference')->unique();
            $table->string('external_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8);
            $table->string('entity', 32)->nullable();
            $table->string('status', 32);
            $table->json('instructions')->nullable();
            $table->json('audit')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index('status');
        });

        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contract_number')->nullable()->unique();
            $table->string('tenant_name');
            $table->string('tenant_phone');
            $table->string('tenant_nif');
            $table->date('start_date');
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedBigInteger('monthly_rent');
            $table->decimal('platform_pct', 5, 2);
            $table->unsignedBigInteger('platform_fee');
            $table->unsignedBigInteger('tenant_total');
            $table->decimal('agent_commission_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('agent_commission_amount')->default(0);
            $table->string('status', 32);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('pct', 5, 2);
            $table->unsignedBigInteger('amount');
            $table->string('status', 32);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('community_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('tipologia', 8);
            $table->string('municipality');
            $table->string('neighborhood');
            $table->unsignedBigInteger('budget')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 16);
            $table->timestamps();
        });

        Schema::create('community_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('accepted');
            $table->timestamps();
            $table->unique(['community_request_id', 'property_id', 'user_id'], 'community_offers_unique');
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id');
            $table->string('status', 32);
            $table->json('payload_meta')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('community_offers');
        Schema::dropIfExists('community_requests');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('rentals');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('agent_links');
        Schema::dropIfExists('property_media');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('settings');
    }
};
