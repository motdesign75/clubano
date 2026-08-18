<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('title')->default('Gutschein');
                $table->decimal('original_amount', 10, 2);
                $table->decimal('remaining_amount', 10, 2);
                $table->string('currency', 3)->default('EUR');
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->string('status')->default('active');
                $table->string('buyer_name')->nullable();
                $table->string('recipient_name')->nullable();
                $table->boolean('legacy')->default(false);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('voucher_redemptions')) {
            Schema::create('voucher_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id');
                $table->foreignId('voucher_id');
                $table->foreignId('event_booking_id')->nullable();
                $table->foreignId('event_booking_participant_id')->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('EUR');
                $table->string('redeemed_by_name')->nullable();
                $table->string('redeemed_by_email')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id', 'voucher_redemptions_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('voucher_id', 'voucher_redemptions_voucher_fk')->references('id')->on('vouchers')->cascadeOnDelete();
                $table->foreign('event_booking_id', 'voucher_redemptions_booking_fk')->references('id')->on('event_bookings')->nullOnDelete();
                $table->foreign('event_booking_participant_id', 'voucher_redemptions_participant_fk')->references('id')->on('event_booking_participants')->nullOnDelete();
                $table->index(['tenant_id', 'created_at']);
            });
        }

        Schema::table('event_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('event_bookings', 'gross_amount')) {
                $table->decimal('gross_amount', 10, 2)->default(0)->after('price_per_person');
            }

            if (! Schema::hasColumn('event_bookings', 'voucher_discount_amount')) {
                $table->decimal('voucher_discount_amount', 10, 2)->default(0)->after('gross_amount');
            }
        });

        Schema::table('event_booking_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('event_booking_participants', 'voucher_discount_amount')) {
                $table->decimal('voucher_discount_amount', 10, 2)->default(0)->after('price_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_booking_participants', function (Blueprint $table) {
            if (Schema::hasColumn('event_booking_participants', 'voucher_discount_amount')) {
                $table->dropColumn('voucher_discount_amount');
            }
        });

        Schema::table('event_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('event_bookings', 'voucher_discount_amount')) {
                $table->dropColumn('voucher_discount_amount');
            }

            if (Schema::hasColumn('event_bookings', 'gross_amount')) {
                $table->dropColumn('gross_amount');
            }
        });

        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
