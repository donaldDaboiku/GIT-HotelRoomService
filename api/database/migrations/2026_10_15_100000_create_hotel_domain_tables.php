<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('public_code', 40)->unique();
            $table->string('logo_path')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('address')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->string('timezone', 64)->default('Africa/Lagos');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('hotel_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('welcome_message');
            $table->text('guest_info')->nullable();
            $table->unsignedInteger('service_charge_bps')->default(0);
            $table->unsignedInteger('tax_bps')->default(0);
            $table->boolean('allow_cash')->default(true);
            $table->boolean('allow_reception_payment')->default(true);
            $table->boolean('allow_online_payment')->default(false);
            $table->boolean('feedback_revisions')->default(false);
            $table->boolean('guest_messages_enabled')->default(true);
            $table->boolean('block_outside_hours')->default(false);
            $table->json('service_hours');
            $table->json('notification_preferences');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();
            $table->unique(['hotel_id', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 32);
            $table->string('status', 20)->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->index(['hotel_id', 'role']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->string('floor', 20)->nullable();
            $table->string('room_type', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hotel_id', 'number']);
            $table->index(['hotel_id', 'is_active']);
        });

        Schema::create('room_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('public_code', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'room_id']);
        });

        DB::statement("CREATE UNIQUE INDEX room_qr_tokens_one_active_per_room ON room_qr_tokens (room_id) WHERE status = 'active'");

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['hotel_id', 'name']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('target_minutes')->nullable();
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['hotel_id', 'is_available']);
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['hotel_id', 'name']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->string('image_path')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_sold_out')->default(false);
            $table->string('dietary_info')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['hotel_id', 'is_available']);
        });

        Schema::create('guest_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'room_id']);
        });

        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_access_token_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('reference', 16)->unique();
            $table->string('status', 32);
            $table->string('priority', 16)->default('normal');
            $table->text('guest_note')->nullable();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('service_charge_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedInteger('service_charge_bps')->default(0);
            $table->unsignedInteger('tax_bps')->default(0);
            $table->char('currency', 3);
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_status', 16)->default('unpaid');
            $table->char('payload_hash', 64)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('outside_service_hours')->default(false);
            $table->timestamps();
            $table->index(['hotel_id', 'status', 'created_at']);
            $table->index(['hotel_id', 'department_id', 'status']);
            $table->index(['hotel_id', 'room_id']);
            $table->index(['guest_access_token_id', 'created_at']);
        });

        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->string('notes', 500)->nullable();
            $table->boolean('is_unavailable')->default(false);
            $table->timestamps();
            $table->index(['hotel_id', 'request_id']);
        });

        Schema::create('request_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['hotel_id', 'assigned_to_user_id']);
        });

        Schema::create('request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->boolean('guest_visible')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['request_id', 'created_at']);
        });

        Schema::create('request_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->boolean('is_guest_visible')->default(false);
            $table->timestamps();
            $table->index(['request_id', 'created_at']);
        });

        Schema::create('guest_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('category', 80)->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->default('in_app');
            $table->string('title');
            $table->text('body');
            $table->string('delivery_status', 32)->default('stored');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 80);
            $table->unsignedInteger('size');
            $table->timestamps();
            $table->index(['hotel_id', 'request_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['hotel_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('hotel_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 40)->default('standard');
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('response_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('request_type', 20);
            $table->unsignedInteger('target_minutes');
            $table->timestamps();
            $table->unique(['hotel_id', 'request_type']);
        });

        $checks = [
            "alter table hotels add constraint hotels_status_check check (status in ('active', 'suspended', 'deactivated'))",
            'alter table hotel_settings add constraint hotel_settings_charge_check check (service_charge_bps between 0 and 10000)',
            'alter table hotel_settings add constraint hotel_settings_tax_check check (tax_bps between 0 and 10000)',
            "alter table users add constraint users_role_check check (role in ('platform_admin', 'hotel_admin', 'receptionist', 'service_staff', 'kitchen_staff'))",
            "alter table users add constraint users_status_check check (status in ('active', 'suspended'))",
            "alter table room_qr_tokens add constraint room_qr_tokens_status_check check (status in ('active', 'revoked'))",
            "alter table service_categories add constraint service_categories_kind_check check (kind in ('housekeeping', 'maintenance', 'reception', 'other'))",
            "alter table requests add constraint requests_type_check check (type in ('service', 'order', 'maintenance'))",
            "alter table requests add constraint requests_status_check check (status in ('submitted', 'acknowledged', 'assigned', 'accepted', 'in_progress', 'preparing', 'ready_for_delivery', 'delivered', 'completed', 'cancelled', 'rejected'))",
            "alter table requests add constraint requests_priority_check check (priority in ('normal', 'high', 'urgent'))",
            "alter table requests add constraint requests_payment_status_check check (payment_status in ('unpaid', 'paid', 'refunded'))",
            'alter table request_items add constraint request_items_quantity_check check (quantity between 1 and 50)',
            'alter table guest_feedback add constraint guest_feedback_rating_check check (rating between 1 and 5)',
            "alter table notifications add constraint notifications_channel_check check (channel in ('in_app'))",
            "alter table notifications add constraint notifications_delivery_check check (delivery_status in ('stored'))",
            "alter table hotel_subscriptions add constraint hotel_subscriptions_status_check check (status in ('active', 'suspended', 'cancelled'))",
            "alter table response_targets add constraint response_targets_type_check check (request_type in ('service', 'order', 'maintenance'))",
            'alter table response_targets add constraint response_targets_minutes_check check (target_minutes between 1 and 10080)',
        ];
        foreach ($checks as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('response_targets');
        Schema::dropIfExists('hotel_subscriptions');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('guest_feedback');
        Schema::dropIfExists('request_internal_notes');
        Schema::dropIfExists('request_status_histories');
        Schema::dropIfExists('request_assignments');
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('guest_access_tokens');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('room_qr_tokens');
        Schema::dropIfExists('rooms');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['hotel_id', 'role']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('hotel_id');
            $table->dropColumn(['role', 'status', 'last_login_at']);
        });

        Schema::dropIfExists('departments');
        Schema::dropIfExists('hotel_settings');
        Schema::dropIfExists('hotels');
    }
};
