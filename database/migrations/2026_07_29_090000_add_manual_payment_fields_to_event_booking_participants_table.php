<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_booking_participants', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
            $table->string('participant_type')->default('guest')->after('contact_id');
            $table->boolean('payment_required')->default(false)->after('phone');
            $table->decimal('price_amount', 10, 2)->default(0)->after('payment_required');
            $table->string('payment_status')->default('not_required')->after('price_amount');
            $table->string('payment_reason')->nullable()->after('payment_status');
            $table->string('source')->default('online')->after('payment_reason');
            $table->text('note')->nullable()->after('source');

            $table->index(['participant_type']);
            $table->index(['payment_status']);
        });

        DB::table('event_booking_participants')
            ->orderBy('id')
            ->chunkById(500, function ($participants) {
                foreach ($participants as $participant) {
                    $booking = DB::table('event_bookings')->where('id', $participant->event_booking_id)->first();

                    if (! $booking) {
                        continue;
                    }

                    $price = (float) ($booking->price_per_person ?? 0);

                    DB::table('event_booking_participants')
                        ->where('id', $participant->id)
                        ->update([
                            'participant_type' => $participant->member_id ? 'member' : 'guest',
                            'payment_required' => $price > 0,
                            'price_amount' => $price,
                            'payment_status' => $price > 0 ? ($booking->payment_status ?: 'open') : 'not_required',
                            'source' => $booking->public_form_submission_id ? 'online' : 'manual',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('event_booking_participants', function (Blueprint $table) {
            $table->dropIndex(['participant_type']);
            $table->dropIndex(['payment_status']);
            $table->dropConstrainedForeignId('contact_id');
            $table->dropColumn([
                'participant_type',
                'payment_required',
                'price_amount',
                'payment_status',
                'payment_reason',
                'source',
                'note',
            ]);
        });
    }
};
