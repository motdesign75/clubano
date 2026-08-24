<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->index(['tenant_id', 'archived_at', 'entry_date'], 'members_tenant_archive_entry_idx');
            $table->index(['tenant_id', 'archived_at', 'exit_date'], 'members_tenant_archive_exit_idx');
            $table->index(['tenant_id', 'archived_at', 'birthday'], 'members_tenant_archive_birthday_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index(['tenant_id', 'start'], 'events_tenant_start_idx');
            $table->index(['tenant_id', 'is_public', 'start'], 'events_tenant_public_start_idx');
        });

        Schema::table('public_form_submissions', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'form_subs_tenant_status_created_idx');
        });

        Schema::table('event_bookings', function (Blueprint $table) {
            $table->index(['tenant_id', 'booking_status', 'created_at'], 'event_bookings_tenant_status_created_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'document_type', 'status', 'due_date'], 'invoices_tenant_type_status_due_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index(['tenant_id', 'archived_at', 'status', 'expires_at'], 'documents_tenant_attention_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_tenant_attention_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_tenant_type_status_due_idx');
        });

        Schema::table('event_bookings', function (Blueprint $table) {
            $table->dropIndex('event_bookings_tenant_status_created_idx');
        });

        Schema::table('public_form_submissions', function (Blueprint $table) {
            $table->dropIndex('form_subs_tenant_status_created_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_tenant_public_start_idx');
            $table->dropIndex('events_tenant_start_idx');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('members_tenant_archive_birthday_idx');
            $table->dropIndex('members_tenant_archive_exit_idx');
            $table->dropIndex('members_tenant_archive_entry_idx');
        });
    }
};
