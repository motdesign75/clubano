<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE public_form_fields
            MODIFY field_type VARCHAR(50) NOT NULL DEFAULT 'text'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE public_form_fields
            SET field_type = 'checkbox'
            WHERE field_type IN ('radio', 'checkbox_group')
        ");

        DB::statement("
            ALTER TABLE public_form_fields
            MODIFY field_type ENUM('text', 'email', 'number', 'date', 'textarea', 'select', 'checkbox')
            NOT NULL DEFAULT 'text'
        ");
    }
};
