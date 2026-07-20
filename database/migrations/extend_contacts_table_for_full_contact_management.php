<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'contact_type')) {
                $table->string('contact_type')->default('person')->after('tenant_id');
            }

            if (!Schema::hasColumn('contacts', 'category')) {
                $table->string('category')->nullable()->after('contact_type');
            }

            if (!Schema::hasColumn('contacts', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('category');
            }

            if (!Schema::hasColumn('contacts', 'is_favorite')) {
                $table->boolean('is_favorite')->default(false)->after('is_active');
            }

            if (!Schema::hasColumn('contacts', 'organization')) {
                $table->string('organization', 150)->nullable()->after('last_name');
            }

            if (!Schema::hasColumn('contacts', 'department')) {
                $table->string('department', 150)->nullable()->after('organization');
            }

            if (!Schema::hasColumn('contacts', 'position')) {
                $table->string('position', 150)->nullable()->after('department');
            }

            if (!Schema::hasColumn('contacts', 'gender')) {
                $table->string('gender', 30)->nullable()->after('position');
            }

            if (!Schema::hasColumn('contacts', 'birthday')) {
                $table->date('birthday')->nullable()->after('title');
            }

            if (!Schema::hasColumn('contacts', 'photo')) {
                $table->string('photo')->nullable()->after('birthday');
            }

            if (!Schema::hasColumn('contacts', 'secondary_email')) {
                $table->string('secondary_email', 190)->nullable()->after('email');
            }

            if (!Schema::hasColumn('contacts', 'mobile')) {
                $table->string('mobile', 50)->nullable()->after('secondary_email');
            }

            if (!Schema::hasColumn('contacts', 'phone')) {
                $table->string('phone', 50)->nullable()->after('mobile');
            }

            if (!Schema::hasColumn('contacts', 'fax')) {
                $table->string('fax', 50)->nullable()->after('phone');
            }

            if (!Schema::hasColumn('contacts', 'address_addition')) {
                $table->string('address_addition', 150)->nullable()->after('street');
            }

            if (!Schema::hasColumn('contacts', 'zip')) {
                $table->string('zip', 20)->nullable()->after('address_addition');
            }

            if (!Schema::hasColumn('contacts', 'state')) {
                $table->string('state', 100)->nullable()->after('city');
            }

            if (!Schema::hasColumn('contacts', 'care_of')) {
                $table->string('care_of', 150)->nullable()->after('country');
            }

            if (!Schema::hasColumn('contacts', 'relationship')) {
                $table->string('relationship', 150)->nullable()->after('source');
            }

            if (!Schema::hasColumn('contacts', 'responsible_user_id')) {
                $table->unsignedBigInteger('responsible_user_id')->nullable()->after('relationship');
            }

            if (!Schema::hasColumn('contacts', 'consent_email')) {
                $table->boolean('consent_email')->default(false)->after('gdpr_consent_at');
            }

            if (!Schema::hasColumn('contacts', 'consent_phone')) {
                $table->boolean('consent_phone')->default(false)->after('consent_email');
            }

            if (!Schema::hasColumn('contacts', 'consent_post')) {
                $table->boolean('consent_post')->default(false)->after('consent_phone');
            }

            if (!Schema::hasColumn('contacts', 'consent_given_at')) {
                $table->timestamp('consent_given_at')->nullable()->after('consent_post');
            }

            if (!Schema::hasColumn('contacts', 'last_contacted_at')) {
                $table->timestamp('last_contacted_at')->nullable()->after('consent_given_at');
            }

            if (!Schema::hasColumn('contacts', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('notes');
            }
        });

        $this->copyExistingContactData();

        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'contact_type')) {
                $table->index(['tenant_id', 'contact_type'], 'contacts_tenant_contact_type_index');
            }

            if (Schema::hasColumn('contacts', 'category')) {
                $table->index(['tenant_id', 'category'], 'contacts_tenant_category_index');
            }

            if (Schema::hasColumn('contacts', 'organization')) {
                $table->index(['tenant_id', 'organization'], 'contacts_tenant_organization_index');
            }

            if (Schema::hasColumn('contacts', 'is_active')) {
                $table->index(['tenant_id', 'is_active'], 'contacts_tenant_is_active_index');
            }

            if (Schema::hasColumn('contacts', 'responsible_user_id')) {
                $table->index('responsible_user_id', 'contacts_responsible_user_id_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'contacts_tenant_contact_type_index');
            $this->dropIndexIfExists($table, 'contacts_tenant_category_index');
            $this->dropIndexIfExists($table, 'contacts_tenant_organization_index');
            $this->dropIndexIfExists($table, 'contacts_tenant_is_active_index');
            $this->dropIndexIfExists($table, 'contacts_responsible_user_id_index');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $columns = [
                'contact_type',
                'category',
                'is_active',
                'is_favorite',
                'organization',
                'department',
                'position',
                'gender',
                'birthday',
                'photo',
                'secondary_email',
                'mobile',
                'phone',
                'fax',
                'address_addition',
                'zip',
                'state',
                'care_of',
                'relationship',
                'responsible_user_id',
                'consent_email',
                'consent_phone',
                'consent_post',
                'consent_given_at',
                'last_contacted_at',
                'internal_notes',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function copyExistingContactData(): void
    {
        if (
            Schema::hasColumn('contacts', 'company') &&
            Schema::hasColumn('contacts', 'organization')
        ) {
            DB::table('contacts')
                ->whereNull('organization')
                ->whereNotNull('company')
                ->update([
                    'organization' => DB::raw('company'),
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'phone_mobile') &&
            Schema::hasColumn('contacts', 'mobile')
        ) {
            DB::table('contacts')
                ->whereNull('mobile')
                ->whereNotNull('phone_mobile')
                ->update([
                    'mobile' => DB::raw('phone_mobile'),
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'phone_landline') &&
            Schema::hasColumn('contacts', 'phone')
        ) {
            DB::table('contacts')
                ->whereNull('phone')
                ->whereNotNull('phone_landline')
                ->update([
                    'phone' => DB::raw('phone_landline'),
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'street_addition') &&
            Schema::hasColumn('contacts', 'address_addition')
        ) {
            DB::table('contacts')
                ->whereNull('address_addition')
                ->whereNotNull('street_addition')
                ->update([
                    'address_addition' => DB::raw('street_addition'),
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'postal_code') &&
            Schema::hasColumn('contacts', 'zip')
        ) {
            DB::table('contacts')
                ->whereNull('zip')
                ->whereNotNull('postal_code')
                ->update([
                    'zip' => DB::raw('postal_code'),
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'status') &&
            Schema::hasColumn('contacts', 'is_active')
        ) {
            DB::table('contacts')
                ->where('status', 'aktiv')
                ->update([
                    'is_active' => true,
                ]);

            DB::table('contacts')
                ->where('status', '!=', 'aktiv')
                ->update([
                    'is_active' => false,
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'gdpr_consent') &&
            Schema::hasColumn('contacts', 'consent_email') &&
            Schema::hasColumn('contacts', 'consent_phone') &&
            Schema::hasColumn('contacts', 'consent_post')
        ) {
            DB::table('contacts')
                ->where('gdpr_consent', true)
                ->update([
                    'consent_email' => true,
                    'consent_phone' => true,
                    'consent_post'  => true,
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'gdpr_consent_at') &&
            Schema::hasColumn('contacts', 'consent_given_at')
        ) {
            DB::table('contacts')
                ->whereNull('consent_given_at')
                ->whereNotNull('gdpr_consent_at')
                ->update([
                    'consent_given_at' => DB::raw('gdpr_consent_at'),
                ]);
        }

        if (
            Schema::hasColumn('contacts', 'organization') &&
            Schema::hasColumn('contacts', 'contact_type')
        ) {
            DB::table('contacts')
                ->whereNotNull('organization')
                ->where(function ($query) {
                    $query->whereNull('first_name')
                        ->orWhere('first_name', '');
                })
                ->where(function ($query) {
                    $query->whereNull('last_name')
                        ->orWhere('last_name', '');
                })
                ->update([
                    'contact_type' => 'organization',
                ]);
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (Throwable $e) {
            //
        }
    }
};
