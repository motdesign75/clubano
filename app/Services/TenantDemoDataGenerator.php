<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\CustomMemberField;
use App\Models\CustomMemberValue;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventBookingParticipant;
use App\Models\EventCategory;
use App\Models\EventShift;
use App\Models\EventShiftAssignment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceNumberRange;
use App\Models\Member;
use App\Models\MemberCommunicationLog;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Protocol;
use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\PublicFormSubmission;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Template;
use App\Models\TemplateDispatchLog;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantDemoDataGenerator
{
    public function run(int $tenantId): array
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        return DB::transaction(function () use ($tenant, $tenantId) {
            $users = $this->seedUsers($tenantId);
            $memberships = $this->seedMemberships($tenantId);
            $tags = $this->seedTags($tenantId);
            [$customFields, $customFieldMap] = $this->seedCustomFields($tenantId);
            $members = $this->seedMembers($tenantId, $memberships, $tags, $customFieldMap, $users);
            $contacts = $this->seedContacts($tenantId, $users);
            $this->seedMemberCommunicationLogs($tenantId, $members, $users);

            $accounts = $this->seedAccounts($tenantId);
            $this->seedTransactions($tenantId, $accounts);
            $this->refreshBalances($accounts);

            $this->seedInvoiceNumberRange($tenantId);
            $invoices = $this->seedInvoices($tenantId, $members, $contacts, $accounts);

            $categories = $this->seedEventCategories($tenantId);
            $events = $this->seedEventsAndBookings($tenant, $tenantId, $categories, $members);

            $forms = $this->seedStandaloneForms($tenantId);
            $templates = $this->seedTemplates($tenantId);
            $this->seedDispatchLogs($tenantId, $templates, $members, $contacts, $users);

            $projects = $this->seedProjectsAndTasks($tenantId, $users);
            $protocols = $this->seedProtocols($tenantId, $users, $members);

            return [
                'tenant' => $tenant->name,
                'users' => count($users),
                'memberships' => count($memberships),
                'tags' => count($tags),
                'custom_fields' => count($customFields),
                'members' => count($members),
                'contacts' => count($contacts),
                'accounts' => count($accounts),
                'invoices' => count($invoices),
                'event_categories' => count($categories),
                'events' => count($events),
                'forms' => count($forms),
                'templates' => count($templates),
                'projects' => count($projects),
                'protocols' => count($protocols),
            ];
        });
    }

    protected function seedUsers(int $tenantId): array
    {
        $definitions = [
            ['name' => 'Demo Vereinsadmin', 'email' => "demo-admin-t{$tenantId}@clubano.test", 'role' => 'Admin'],
            ['name' => 'Demo Event-Orga', 'email' => "demo-events-t{$tenantId}@clubano.test", 'role' => 'Mitarbeiter'],
            ['name' => 'Demo Kasse', 'email' => "demo-finanzen-t{$tenantId}@clubano.test", 'role' => 'Lesen'],
        ];

        $users = [];

        foreach ($definitions as $definition) {
            $users[] = User::query()->firstOrCreate(
                ['email' => $definition['email']],
                [
                    'tenant_id' => $tenantId,
                    'name' => $definition['name'],
                    'password' => Hash::make('demo123!'),
                    'role' => $definition['role'],
                    'email_verified_at' => now(),
                ]
            );
        }

        return $users;
    }

    protected function seedMemberships(int $tenantId): array
    {
        $definitions = [
            ['name' => 'Erwachsene', 'amount' => 72.00, 'interval' => 'jährlich'],
            ['name' => 'Familie', 'amount' => 120.00, 'interval' => 'jährlich'],
            ['name' => 'Jugend', 'amount' => 36.00, 'interval' => 'jährlich'],
            ['name' => 'Fördermitglied', 'amount' => 48.00, 'interval' => 'jährlich'],
        ];

        $memberships = [];

        foreach ($definitions as $definition) {
            $memberships[] = Membership::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $definition['name']],
                $definition + ['tenant_id' => $tenantId]
            );
        }

        return $memberships;
    }

    protected function seedTags(int $tenantId): array
    {
        $definitions = [
            ['name' => 'Vorstand', 'color' => '#4338CA'],
            ['name' => 'Brauteam', 'color' => '#D97706'],
            ['name' => 'Helferpool', 'color' => '#059669'],
            ['name' => 'Newsletter', 'color' => '#2563EB'],
            ['name' => 'Ehrenamt', 'color' => '#DB2777'],
        ];

        $tags = [];

        foreach ($definitions as $definition) {
            $tags[] = Tag::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $definition['name']],
                $definition + ['tenant_id' => $tenantId]
            );
        }

        return $tags;
    }

    protected function seedCustomFields(int $tenantId): array
    {
        $definitions = [
            [
                'name' => 'T-Shirt-Größe',
                'slug' => 'tshirt_groesse',
                'type' => 'select',
                'options' => json_encode(['S', 'M', 'L', 'XL']),
                'required' => false,
                'visible' => true,
                'order' => 1,
            ],
            [
                'name' => 'Brauerfahrung',
                'slug' => 'brauerfahrung',
                'type' => 'text',
                'options' => null,
                'required' => false,
                'visible' => true,
                'order' => 2,
            ],
            [
                'name' => 'Führerschein vorhanden',
                'slug' => 'fuehrerschein_vorhanden',
                'type' => 'checkbox',
                'options' => null,
                'required' => false,
                'visible' => true,
                'order' => 3,
            ],
        ];

        $fields = [];
        $map = [];

        foreach ($definitions as $definition) {
            $field = CustomMemberField::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $definition['slug']],
                $definition + ['tenant_id' => $tenantId]
            );

            $fields[] = $field;
            $map[$field->slug] = $field;
        }

        return [$fields, $map];
    }

    protected function seedMembers(int $tenantId, array $memberships, array $tags, array $customFieldMap, array $users): array
    {
        $membershipByName = collect($memberships)->keyBy('name');
        $tagByName = collect($tags)->keyBy('name');

        $rows = [
            ['id' => 'DEMO-2001', 'first' => 'Maik-Oliver', 'last' => 'Towet', 'membership' => 'Erwachsene', 'entry' => now()->subYears(7)->setDate(now()->year, 5, 2), 'birthday' => now()->addDays(12)->subYears(42), 'status' => 'active', 'email' => 'maik-oliver.towet@example.test'],
            ['id' => 'DEMO-2002', 'first' => 'Anna', 'last' => 'Hopfen', 'membership' => 'Familie', 'entry' => now()->subYears(3)->setDate(now()->year, 6, 15), 'birthday' => now()->addDays(25)->subYears(38), 'status' => 'active', 'email' => 'anna.hopfen@example.test'],
            ['id' => 'DEMO-2003', 'first' => 'Dirk', 'last' => 'Radecker', 'membership' => 'Erwachsene', 'entry' => now()->subYears(5)->setDate(now()->year, 7, 1), 'birthday' => now()->addDays(40)->subYears(49), 'status' => 'active', 'email' => 'dirk.radecker@example.test'],
            ['id' => 'DEMO-2004', 'first' => 'Marcus', 'last' => 'Braugerste', 'membership' => 'Fördermitglied', 'entry' => now()->subYears(1), 'birthday' => now()->addDays(8)->subYears(56), 'status' => 'active', 'email' => 'marcus.braugerste@example.test'],
            ['id' => 'DEMO-2005', 'first' => 'Ruben', 'last' => 'Malz', 'membership' => 'Erwachsene', 'entry' => now()->startOfYear()->addDays(9), 'birthday' => now()->addDays(65)->subYears(34), 'status' => 'active', 'email' => 'ruben.malz@example.test'],
            ['id' => 'DEMO-2006', 'first' => 'Lena', 'last' => 'Sudhaus', 'membership' => 'Jugend', 'entry' => now()->startOfYear()->addDays(34), 'birthday' => now()->addDays(18)->subYears(19), 'status' => 'active', 'email' => 'lena.sudhaus@example.test'],
            ['id' => 'DEMO-2007', 'first' => 'Tom', 'last' => 'Braukessel', 'membership' => 'Jugend', 'entry' => now()->startOfYear()->addDays(58), 'birthday' => now()->addDays(90)->subYears(17), 'status' => 'active', 'email' => 'tom.braukessel@example.test'],
            ['id' => 'DEMO-2008', 'first' => 'Sina', 'last' => 'Pils', 'membership' => 'Erwachsene', 'entry' => now()->subMonths(9), 'birthday' => now()->addDays(4)->subYears(29), 'status' => 'active', 'email' => 'sina.pils@example.test'],
            ['id' => 'DEMO-2009', 'first' => 'Jörg', 'last' => 'Gerste', 'membership' => 'Erwachsene', 'entry' => now()->subYears(12), 'birthday' => now()->addDays(33)->subYears(61), 'status' => 'active', 'email' => 'joerg.gerste@example.test'],
            ['id' => 'DEMO-2010', 'first' => 'Klara', 'last' => 'Kühlhaus', 'membership' => 'Familie', 'entry' => now()->subYears(2), 'birthday' => now()->addDays(72)->subYears(45), 'status' => 'active', 'email' => 'klara.kuehlhaus@example.test'],
            ['id' => 'DEMO-2011', 'first' => 'Ben', 'last' => 'Zapfhahn', 'membership' => 'Jugend', 'entry' => now()->addMonths(2), 'birthday' => now()->addDays(55)->subYears(15), 'status' => 'future', 'email' => 'ben.zapfhahn@example.test'],
            ['id' => 'DEMO-2012', 'first' => 'Julia', 'last' => 'Schank', 'membership' => 'Fördermitglied', 'entry' => now()->subYears(4), 'birthday' => now()->addDays(104)->subYears(41), 'status' => 'active', 'email' => 'julia.schank@example.test'],
            ['id' => 'DEMO-2013', 'first' => 'Paul', 'last' => 'Hopfenblatt', 'membership' => 'Erwachsene', 'entry' => now()->subYears(6), 'birthday' => now()->addDays(28)->subYears(47), 'status' => 'active', 'email' => 'paul.hopfenblatt@example.test'],
            ['id' => 'DEMO-2014', 'first' => 'Mara', 'last' => 'Goldbier', 'membership' => 'Erwachsene', 'entry' => now()->subYears(8), 'birthday' => now()->addDays(14)->subYears(52), 'status' => 'active', 'email' => 'mara.goldbier@example.test'],
            ['id' => 'DEMO-2015', 'first' => 'Theo', 'last' => 'Fass', 'membership' => 'Erwachsene', 'entry' => now()->subYears(10), 'birthday' => now()->addDays(120)->subYears(64), 'status' => 'former', 'email' => 'theo.fass@example.test'],
            ['id' => 'DEMO-2016', 'first' => 'Nina', 'last' => 'Bottich', 'membership' => 'Fördermitglied', 'entry' => now()->subYears(5), 'birthday' => now()->addDays(160)->subYears(36), 'status' => 'former', 'email' => 'nina.bottich@example.test'],
            ['id' => 'DEMO-2017', 'first' => 'Hannes', 'last' => 'Hefe', 'membership' => 'Erwachsene', 'entry' => now()->subYears(2), 'birthday' => now()->addDays(50)->subYears(33), 'status' => 'archived', 'email' => 'hannes.hefe@example.test'],
            ['id' => 'DEMO-2018', 'first' => 'Eva', 'last' => 'Kellerbier', 'membership' => 'Familie', 'entry' => now()->subMonths(4), 'birthday' => now()->addDays(11)->subYears(39), 'status' => 'active', 'email' => 'eva.kellerbier@example.test'],
        ];

        $members = [];

        foreach ($rows as $index => $row) {
            $membership = $membershipByName[$row['membership']];
            $member = Member::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'member_id' => $row['id']],
                [
                    'tenant_id' => $tenantId,
                    'gender' => $index % 2 === 0 ? 'männlich' : 'weiblich',
                    'salutation' => $index % 2 === 0 ? 'Herr' : 'Frau',
                    'first_name' => $row['first'],
                    'last_name' => $row['last'],
                    'birthday' => Carbon::parse($row['birthday'])->toDateString(),
                    'photo' => null,
                    'entry_date' => Carbon::parse($row['entry'])->toDateString(),
                    'membership_id' => $membership->id,
                    'membership_amount' => $membership->amount,
                    'membership_interval' => $membership->interval,
                    'email' => $row['email'],
                    'mobile' => '0176' . str_pad((string) (2000000 + $index), 7, '0', STR_PAD_LEFT),
                    'whatsapp_phone' => '0176' . str_pad((string) (2100000 + $index), 7, '0', STR_PAD_LEFT),
                    'landline' => '05066 ' . (300000 + $index),
                    'preferred_contact_channel' => $index % 3 === 0 ? 'email' : ($index % 3 === 1 ? 'phone' : 'whatsapp'),
                    'consent_email' => true,
                    'consent_phone' => true,
                    'consent_post' => true,
                    'consent_whatsapp' => $index % 2 === 0,
                    'consent_data_processing' => true,
                    'consent_photo_internal' => true,
                    'consent_photo_public' => $index % 4 !== 0,
                    'consent_given_at' => now()->subMonths(8),
                    'last_contacted_at' => now()->subDays(rand(3, 45)),
                    'street' => 'Hopfenweg ' . ($index + 1),
                    'zip' => '31157',
                    'city' => 'Sarstedt',
                    'country' => 'DE',
                ]
            );

            if ($row['status'] === 'former') {
                $member->forceFill([
                    'exit_date' => now()->subMonths(2)->toDateString(),
                    'termination_date' => now()->subMonths(3)->toDateString(),
                ])->save();
            } elseif ($row['status'] === 'future') {
                $member->forceFill([
                    'entry_date' => now()->addMonths(2)->toDateString(),
                ])->save();
            } elseif ($row['status'] === 'archived') {
                $member->forceFill([
                    'archived_at' => now()->subDays(40),
                    'deletion_requested_at' => now()->subDays(60),
                    'deletion_note' => 'Archivierter Testdatensatz für Datenschutz- und Vereinsalltag.',
                ])->save();
            }

            $members[] = $member;
        }

        $tagAssignments = [
            'Vorstand' => ['DEMO-2001', 'DEMO-2002'],
            'Brauteam' => ['DEMO-2001', 'DEMO-2003', 'DEMO-2009', 'DEMO-2013'],
            'Helferpool' => ['DEMO-2004', 'DEMO-2008', 'DEMO-2010', 'DEMO-2018'],
            'Newsletter' => ['DEMO-2002', 'DEMO-2005', 'DEMO-2012', 'DEMO-2014'],
            'Ehrenamt' => ['DEMO-2001', 'DEMO-2003', 'DEMO-2004', 'DEMO-2013'],
        ];

        $membersById = collect($members)->keyBy('member_id');

        foreach ($tagAssignments as $tagName => $memberIds) {
            $tag = $tagByName[$tagName];

            foreach ($memberIds as $memberId) {
                $member = $membersById[$memberId] ?? null;

                if ($member) {
                    $member->tags()->syncWithoutDetaching([$tag->id]);
                }
            }
        }

        $customValues = [
            'DEMO-2001' => ['tshirt_groesse' => 'XL', 'brauerfahrung' => 'Langjähriger Hobbybrauer', 'fuehrerschein_vorhanden' => '1'],
            'DEMO-2002' => ['tshirt_groesse' => 'M', 'brauerfahrung' => 'Organisation & Ausschank', 'fuehrerschein_vorhanden' => '1'],
            'DEMO-2006' => ['tshirt_groesse' => 'S', 'brauerfahrung' => 'Neugierig, erstes Braujahr', 'fuehrerschein_vorhanden' => '0'],
            'DEMO-2013' => ['tshirt_groesse' => 'L', 'brauerfahrung' => 'Sudhaus und Technik', 'fuehrerschein_vorhanden' => '1'],
        ];

        foreach ($customValues as $memberId => $values) {
            $member = $membersById[$memberId] ?? null;

            if (! $member) {
                continue;
            }

            foreach ($values as $slug => $value) {
                $field = $customFieldMap[$slug] ?? null;

                if ($field) {
                    CustomMemberValue::query()->updateOrCreate(
                        [
                            'member_id' => $member->id,
                            'custom_member_field_id' => $field->id,
                        ],
                        ['value' => $value]
                    );
                }
            }
        }

        return $members;
    }

    protected function seedContacts(int $tenantId, array $users): array
    {
        $responsibleUser = $users[0] ?? null;
        $definitions = [
            ['organization' => 'Stadt Sarstedt', 'first_name' => 'Petra', 'last_name' => 'Kultur', 'category' => 'oeffentlich', 'email' => 'petra.kultur@example.test'],
            ['organization' => 'Getränkehaus Hildesheim', 'first_name' => 'Sven', 'last_name' => 'Lieferant', 'category' => 'lieferant', 'email' => 'sven.lieferant@example.test'],
            ['organization' => 'Volksbank Hannover', 'first_name' => 'Lars', 'last_name' => 'Bank', 'category' => 'partner', 'email' => 'lars.bank@example.test'],
            ['organization' => 'Feuerwehr Sarstedt', 'first_name' => 'Mila', 'last_name' => 'Einsatz', 'category' => 'partner', 'email' => 'mila.einsatz@example.test'],
            ['organization' => 'Brauhaus Nord', 'first_name' => 'Jana', 'last_name' => 'Sponsoring', 'category' => 'sponsor', 'email' => 'jana.sponsoring@example.test'],
            ['organization' => 'Kulturverein Leine', 'first_name' => 'Ralf', 'last_name' => 'Netzwerk', 'category' => 'netzwerk', 'email' => 'ralf.netzwerk@example.test'],
        ];

        $contacts = [];

        foreach ($definitions as $index => $definition) {
            $contacts[] = Contact::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $definition['email']],
                [
                    'tenant_id' => $tenantId,
                    'contact_type' => 'organization',
                    'category' => $definition['category'],
                    'is_active' => true,
                    'is_favorite' => $index < 2,
                    'organization' => $definition['organization'],
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                    'email' => $definition['email'],
                    'mobile' => '0175' . str_pad((string) (5000000 + $index), 7, '0', STR_PAD_LEFT),
                    'phone' => '05121 ' . (700000 + $index),
                    'street' => 'Partnerstraße ' . ($index + 1),
                    'zip' => '31157',
                    'city' => 'Sarstedt',
                    'country' => 'DE',
                    'relationship' => 'Vereinskontakt',
                    'source' => 'Demo-Testdaten',
                    'responsible_user_id' => $responsibleUser?->id,
                    'consent_email' => true,
                    'consent_phone' => true,
                    'consent_post' => true,
                    'consent_given_at' => now()->subMonths(6),
                    'last_contacted_at' => now()->subDays(10 + $index),
                    'notes' => 'Automatisch erzeugter Testkontakt.',
                ]
            );
        }

        return $contacts;
    }

    protected function seedMemberCommunicationLogs(int $tenantId, array $members, array $users): void
    {
        $examples = [
            ['member_index' => 0, 'channel' => 'email', 'subject' => 'Willkommen im Brauteam', 'message' => 'Schön, dass du wieder beim Maisud dabei bist.'],
            ['member_index' => 1, 'channel' => 'whatsapp', 'subject' => 'Helferabfrage Maifest', 'message' => 'Kannst du die Frühschicht am Ausschank übernehmen?'],
            ['member_index' => 4, 'channel' => 'phone', 'subject' => 'Rückfrage zur Anmeldung', 'message' => 'Teilnehmerzahl für den Braukurs abgestimmt.'],
        ];

        foreach ($examples as $index => $example) {
            $member = $members[$example['member_index']] ?? null;
            $user = $users[$index % max(count($users), 1)] ?? null;

            if (! $member || ! $user) {
                continue;
            }

            MemberCommunicationLog::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'member_id' => $member->id,
                    'subject' => $example['subject'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'member_id' => $member->id,
                    'created_by' => $user->id,
                    'channel' => $example['channel'],
                    'direction' => 'outbound',
                    'recipient' => $member->email ?: $member->mobile,
                    'message' => $example['message'],
                    'sent_at' => now()->subDays(5 + $index),
                ]
            );
        }
    }

    protected function seedAccounts(int $tenantId): array
    {
        $definitions = [
            ['number' => '1000', 'name' => 'Vereinskonto', 'type' => 'bank', 'tax_area' => 'ideell', 'balance_start' => 5400, 'balance_date' => now()->startOfYear()->toDateString()],
            ['number' => '1010', 'name' => 'Barkasse', 'type' => 'kasse', 'tax_area' => 'ideell', 'balance_start' => 320, 'balance_date' => now()->startOfYear()->toDateString()],
            ['number' => '4000', 'name' => 'Mitgliedsbeiträge', 'type' => 'einnahme', 'tax_area' => 'ideell', 'balance_start' => 0, 'balance_date' => now()->startOfYear()->toDateString()],
            ['number' => '4100', 'name' => 'Eventeinnahmen', 'type' => 'einnahme', 'tax_area' => 'wirtschaftlich', 'balance_start' => 0, 'balance_date' => now()->startOfYear()->toDateString()],
            ['number' => '4200', 'name' => 'Sponsoring', 'type' => 'einnahme', 'tax_area' => 'ideell', 'balance_start' => 0, 'balance_date' => now()->startOfYear()->toDateString()],
            ['number' => '6000', 'name' => 'Wareneinkauf', 'type' => 'ausgabe', 'tax_area' => 'wirtschaftlich', 'balance_start' => 0, 'balance_date' => now()->startOfYear()->toDateString()],
            ['number' => '6100', 'name' => 'Miete & Infrastruktur', 'type' => 'ausgabe', 'tax_area' => 'ideell', 'balance_start' => 0, 'balance_date' => now()->startOfYear()->toDateString()],
            ['number' => '6200', 'name' => 'Öffentlichkeitsarbeit', 'type' => 'ausgabe', 'tax_area' => 'zweckbetrieb', 'balance_start' => 0, 'balance_date' => now()->startOfYear()->toDateString()],
        ];

        $accounts = [];

        foreach ($definitions as $definition) {
            $accounts[] = Account::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'number' => $definition['number']],
                $definition + ['tenant_id' => $tenantId, 'active' => true, 'online' => false, 'balance_current' => $definition['balance_start']]
            );
        }

        return $accounts;
    }

    protected function seedTransactions(int $tenantId, array $accounts): void
    {
        $map = collect($accounts)->keyBy('number');
        $transactions = [
            ['date' => now()->startOfYear()->addDays(3), 'description' => 'Mitgliedsbeiträge Januar', 'amount' => 420.00, 'from' => '4000', 'to' => '1000', 'tax_area' => 'ideell', 'receipt' => 'T2-2026-001'],
            ['date' => now()->startOfYear()->addDays(11), 'description' => 'Einkauf Rohstoffe Braukurs', 'amount' => 185.40, 'from' => '1000', 'to' => '6000', 'tax_area' => 'wirtschaftlich', 'receipt' => 'T2-2026-002'],
            ['date' => now()->startOfYear()->addDays(18), 'description' => 'Sponsoring Brauhaus Nord', 'amount' => 600.00, 'from' => '4200', 'to' => '1000', 'tax_area' => 'ideell', 'receipt' => 'T2-2026-003'],
            ['date' => now()->startOfYear()->addDays(26), 'description' => 'Flyer & Plakate', 'amount' => 96.50, 'from' => '1000', 'to' => '6200', 'tax_area' => 'zweckbetrieb', 'receipt' => 'T2-2026-004'],
            ['date' => now()->subMonths(2)->startOfMonth()->addDays(2), 'description' => 'Eintritt Braukurs Frühling', 'amount' => 295.00, 'from' => '4100', 'to' => '1000', 'tax_area' => 'wirtschaftlich', 'receipt' => 'T2-2026-005'],
            ['date' => now()->subMonths(2)->startOfMonth()->addDays(7), 'description' => 'Getränkeeinkauf Maifest', 'amount' => 240.00, 'from' => '1000', 'to' => '6000', 'tax_area' => 'wirtschaftlich', 'receipt' => 'T2-2026-006'],
            ['date' => now()->subMonth()->startOfMonth()->addDays(4), 'description' => 'Mitgliedsbeiträge April', 'amount' => 420.00, 'from' => '4000', 'to' => '1000', 'tax_area' => 'ideell', 'receipt' => 'T2-2026-007'],
            ['date' => now()->subMonth()->startOfMonth()->addDays(10), 'description' => 'Kassenauslage Deko', 'amount' => 44.90, 'from' => '1010', 'to' => '6200', 'tax_area' => 'zweckbetrieb', 'receipt' => 'T2-2026-008'],
            ['date' => now()->subMonth()->startOfMonth()->addDays(14), 'description' => 'Raummiete Brauabend', 'amount' => 150.00, 'from' => '1000', 'to' => '6100', 'tax_area' => 'ideell', 'receipt' => 'T2-2026-009'],
            ['date' => now()->addDays(2), 'description' => 'Frühbucher Braukurs Sommer', 'amount' => 118.00, 'from' => '4100', 'to' => '1000', 'tax_area' => 'wirtschaftlich', 'receipt' => 'T2-2026-010'],
        ];

        foreach ($transactions as $entry) {
            Transaction::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'receipt_number' => $entry['receipt']],
                [
                    'tenant_id' => $tenantId,
                    'date' => Carbon::parse($entry['date'])->toDateString(),
                    'description' => $entry['description'],
                    'amount' => $entry['amount'],
                    'account_from_id' => $map[$entry['from']]->id,
                    'account_to_id' => $map[$entry['to']]->id,
                    'tax_area' => $entry['tax_area'],
                ]
            );
        }
    }

    protected function refreshBalances(array $accounts): void
    {
        foreach ($accounts as $account) {
            $account->updateBalance();
        }
    }

    protected function seedInvoiceNumberRange(int $tenantId): void
    {
        InvoiceNumberRange::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'type' => 'beitrag'],
            [
                'tenant_id' => $tenantId,
                'type' => 'beitrag',
                'prefix' => 'RE-2026-',
                'suffix' => '',
                'start_number' => 1,
                'current_number' => 3,
                'reset_yearly' => true,
            ]
        );
    }

    protected function seedInvoices(int $tenantId, array $members, array $contacts, array $accounts): array
    {
        $memberById = collect($members)->keyBy('member_id');
        $fallbackMember = $members[0] ?? null;
        $contactByEmail = collect($contacts)->keyBy('email');
        $bank = collect($accounts)->firstWhere('number', '1000');
        $invoices = [];

        $definitions = [
            [
                'invoice_number' => 'RE-2026-0001',
                'recipient_type' => 'member',
                'member_id' => 'DEMO-2001',
                'status' => 'paid',
                'items' => [
                    ['description' => 'Mitgliedsbeitrag 2026', 'quantity' => 1, 'unit' => 'Jahr', 'unit_price' => 72.00, 'tax_rate' => 0, 'discount' => 0],
                ],
            ],
            [
                'invoice_number' => 'RE-2026-0002',
                'recipient_type' => 'member',
                'member_id' => 'DEMO-2002',
                'status' => 'open',
                'items' => [
                    ['description' => 'Familienbeitrag 2026', 'quantity' => 1, 'unit' => 'Jahr', 'unit_price' => 120.00, 'tax_rate' => 0, 'discount' => 0],
                ],
            ],
            [
                'invoice_number' => 'RE-2026-0003',
                'recipient_type' => 'contact',
                'contact_email' => 'jana.sponsoring@example.test',
                'status' => 'open',
                'items' => [
                    ['description' => 'Sponsoring-Paket Maifest', 'quantity' => 1, 'unit' => 'Paket', 'unit_price' => 250.00, 'tax_rate' => 19, 'discount' => 0],
                ],
            ],
            [
                'invoice_number' => 'RE-2026-0004',
                'recipient_type' => 'free',
                'recipient_name' => 'Eventservice Leinetal',
                'recipient_company' => 'Eventservice Leinetal GmbH',
                'recipient_email' => 'buchhaltung@eventservice-leinetal.example',
                'recipient_street' => 'Messeweg 8',
                'recipient_zip' => '31134',
                'recipient_city' => 'Hildesheim',
                'status' => 'entwurf',
                'items' => [
                    ['description' => 'Standmiete Vereinsfest', 'quantity' => 1, 'unit' => 'Tag', 'unit_price' => 180.00, 'tax_rate' => 19, 'discount' => 0],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $member = isset($definition['member_id']) ? ($memberById[$definition['member_id']] ?? null) : null;
            $contact = isset($definition['contact_email']) ? ($contactByEmail[$definition['contact_email']] ?? null) : null;

            $invoice = Invoice::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'invoice_number' => $definition['invoice_number']],
                [
                    'tenant_id' => $tenantId,
                    'member_id' => $member?->id ?: $fallbackMember?->id,
                    'contact_id' => $contact?->id,
                    'recipient_type' => $definition['recipient_type'],
                    'recipient_name' => $member?->full_name ?: $contact?->full_name ?: $definition['recipient_name'],
                    'recipient_company' => $contact?->organization ?: ($definition['recipient_company'] ?? null),
                    'recipient_salutation' => $member?->salutation ?: $contact?->salutation ?: null,
                    'recipient_email' => $member?->email ?: $contact?->email ?: ($definition['recipient_email'] ?? null),
                    'recipient_street' => $member?->street ?: $contact?->street ?: ($definition['recipient_street'] ?? null),
                    'recipient_zip' => $member?->zip ?: $contact?->zip ?: ($definition['recipient_zip'] ?? null),
                    'recipient_city' => $member?->city ?: $contact?->city ?: ($definition['recipient_city'] ?? null),
                    'recipient_country' => $member?->country ?: $contact?->country ?: 'DE',
                    'intro_text' => 'vielen Dank für eure Unterstützung und euren Beitrag zum Vereinsleben.',
                    'payment_text' => 'Bitte überweist den Rechnungsbetrag fristgerecht auf das Vereinskonto.',
                    'closing_text' => 'Mit hopfigen Grüßen',
                    'invoice_date' => now()->subDays(15)->toDateString(),
                    'due_date' => now()->addDays(14)->toDateString(),
                    'period_year' => now()->year,
                    'period_from' => now()->startOfYear()->toDateString(),
                    'period_to' => now()->endOfYear()->toDateString(),
                    'status' => $definition['status'],
                    'amount' => collect($definition['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']),
                    'description' => collect($definition['items'])->pluck('description')->implode(', '),
                    'discount' => 0,
                    'tax_rate' => $definition['items'][0]['tax_rate'],
                    'total' => collect($definition['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']),
                    'paid_at' => $definition['status'] === 'paid' ? now()->subDays(5) : null,
                ]
            );

            foreach ($definition['items'] as $position => $item) {
                InvoiceItem::query()->firstOrCreate(
                    [
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                    ],
                    $item
                );
            }

            if ($definition['status'] === 'paid' && $bank) {
                Payment::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->total,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'invoice_id' => $invoice->id,
                        'account_id' => $bank->id,
                        'payment_date' => now()->subDays(5)->toDateString(),
                        'note' => 'Automatisch erzeugte Testzahlung.',
                    ]
                );
            }

            $invoices[] = $invoice;
        }

        return $invoices;
    }

    protected function seedEventCategories(int $tenantId): array
    {
        $definitions = [
            ['name' => 'Braukurse', 'slug' => 'braukurse', 'color' => '#EF4444'],
            ['name' => 'Vereinsleben', 'slug' => 'vereinsleben', 'color' => '#84CC16'],
            ['name' => 'Öffentliche Feste', 'slug' => 'oeffentliche-feste', 'color' => '#F59E0B'],
        ];

        $categories = [];

        foreach ($definitions as $definition) {
            $categories[] = EventCategory::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $definition['slug']],
                $definition + ['tenant_id' => $tenantId]
            );
        }

        return $categories;
    }

    protected function seedEventsAndBookings(Tenant $tenant, int $tenantId, array $categories, array $members): array
    {
        $categoryBySlug = collect($categories)->keyBy('slug');
        $membersById = collect($members)->keyBy('member_id');
        $events = [];

        $definitions = [
            [
                'title' => 'Braukurs – Brauen wie vor 350 Jahren',
                'slug' => 'braukurs-350',
                'category' => 'braukurse',
                'start' => now()->addDays(6)->setTime(10, 0),
                'end' => now()->addDays(6)->setTime(16, 0),
                'location' => 'Lönsstraße, 31157 Sarstedt',
                'is_public' => true,
                'booking_enabled' => true,
                'price_per_person' => 59.00,
                'max_booking' => 4,
                'description' => '<p>Ein öffentlicher Braukurs mit Fokus auf historische Braumethoden.</p>',
            ],
            [
                'title' => 'GemeinsamZeit bei den Sarstedter Bierfreunden',
                'slug' => 'gemeinsamzeit',
                'category' => 'vereinsleben',
                'start' => now()->addDays(14)->setTime(18, 30),
                'end' => now()->addDays(14)->setTime(22, 0),
                'location' => 'Vereinsheim Sarstedt',
                'is_public' => true,
                'booking_enabled' => true,
                'price_per_person' => 0,
                'max_booking' => 6,
                'description' => '<p>Locker zusammenkommen, Themen besprechen, neue Ideen sammeln.</p>',
            ],
            [
                'title' => 'Maifest Ausschank & Aufbau',
                'slug' => 'maifest-ausschank',
                'category' => 'oeffentliche-feste',
                'start' => now()->addDays(3)->setTime(11, 0),
                'end' => now()->addDays(3)->setTime(19, 0),
                'location' => 'Innenstadt Sarstedt',
                'is_public' => false,
                'booking_enabled' => false,
                'price_per_person' => 0,
                'max_booking' => 1,
                'description' => '<p>Interne Planung für Aufbau, Ausschank und Abbau.</p>',
            ],
            [
                'title' => 'Trockenfilzkurs – Kreatives Gestalten',
                'slug' => 'trockenfilzkurs',
                'category' => 'oeffentliche-feste',
                'start' => now()->addDays(23)->setTime(14, 30),
                'end' => now()->addDays(23)->setTime(18, 0),
                'location' => 'Lönsstraße, 31157 Sarstedt',
                'is_public' => true,
                'booking_enabled' => true,
                'price_per_person' => 25.00,
                'max_booking' => 5,
                'description' => '<p>Kooperationskurs mit kreativem Schwerpunkt und offener Anmeldung.</p>',
            ],
        ];

        foreach ($definitions as $definition) {
            $event = Event::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'title' => $definition['title']],
                [
                    'tenant_id' => $tenantId,
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'location' => $definition['location'],
                    'start' => Carbon::parse($definition['start']),
                    'end' => Carbon::parse($definition['end']),
                    'is_public' => $definition['is_public'],
                    'booking_enabled' => $definition['booking_enabled'],
                    'price_per_person' => $definition['price_per_person'],
                    'currency' => 'EUR',
                    'max_participants_per_booking' => $definition['max_booking'],
                    'category_id' => $categoryBySlug[$definition['category']]->id,
                ]
            );

            if ($definition['booking_enabled']) {
                $this->seedEventBookingForm($tenantId, $event);
            }

            if ($definition['title'] === 'Braukurs – Brauen wie vor 350 Jahren') {
                $this->seedEventBookings($tenant, $tenantId, $event, [
                    ['reference' => 'EVT2-BRAU-001', 'name' => 'Anna Hopfen', 'email' => 'anna.hopfen@example.test', 'phone' => '01761230001', 'count' => 2, 'status' => 'confirmed', 'payment' => 'paid', 'participants' => [['Anna', 'Hopfen'], ['Jonas', 'Hopfen']]],
                    ['reference' => 'EVT2-BRAU-002', 'name' => 'Dirk Radecker', 'email' => 'dirk.radecker@example.test', 'phone' => '01761230002', 'count' => 1, 'status' => 'pending', 'payment' => 'open', 'participants' => [['Dirk', 'Radecker']]],
                ]);
            }

            if ($definition['title'] === 'GemeinsamZeit bei den Sarstedter Bierfreunden') {
                $this->seedEventBookings($tenant, $tenantId, $event, [
                    ['reference' => 'EVT2-GEM-001', 'name' => 'Maik-Oliver Towet', 'email' => 'maik-oliver.towet@example.test', 'phone' => '01761230003', 'count' => 3, 'status' => 'confirmed', 'payment' => 'not_required', 'participants' => [['Maik-Oliver', 'Towet'], ['Mara', 'Goldbier'], ['Paul', 'Hopfenblatt']]],
                ]);
            }

            if ($definition['title'] === 'Maifest Ausschank & Aufbau') {
                $this->seedEventShifts($tenantId, $event, $membersById);
            }

            $events[] = $event;
        }

        return $events;
    }

    protected function seedEventBookingForm(int $tenantId, Event $event): void
    {
        $form = PublicForm::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'event_id' => $event->id, 'slug' => Str::slug($event->title)],
            [
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'title' => $event->title . ' – Anmeldung',
                'description' => 'Öffentliche Event-Anmeldung',
                'form_type' => 'event',
                'success_message' => 'Vielen Dank für die Anmeldung.',
                'is_active' => true,
            ]
        );

        $fields = [
            ['label' => 'Vorname', 'slug' => 'vorname', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
            ['label' => 'Nachname', 'slug' => 'nachname', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
            ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 3],
            ['label' => 'Telefon', 'slug' => 'telefon', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
        ];

        foreach ($fields as $field) {
            PublicFormField::query()->firstOrCreate(
                ['public_form_id' => $form->id, 'slug' => $field['slug']],
                $field + ['public_form_id' => $form->id]
            );
        }
    }

    protected function seedEventBookings(Tenant $tenant, int $tenantId, Event $event, array $definitions): void
    {
        $form = PublicForm::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('form_type', 'event')
            ->first();

        foreach ($definitions as $index => $definition) {
            $submission = PublicFormSubmission::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'email' => $definition['email'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'public_form_id' => $form?->id,
                    'event_id' => $event->id,
                    'full_name' => $definition['name'],
                    'email' => $definition['email'],
                    'phone' => $definition['phone'],
                    'answers' => [
                        'booker_name' => $definition['name'],
                        'participant_count' => $definition['count'],
                    ],
                ]
            );

            $booking = EventBooking::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'booking_reference' => $definition['reference'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'public_form_submission_id' => $submission->id,
                    'booker_name' => $definition['name'],
                    'booker_email' => $definition['email'],
                    'booker_phone' => $definition['phone'],
                    'participant_count' => $definition['count'],
                    'price_per_person' => (float) $event->price_per_person,
                    'total_amount' => (float) $event->price_per_person * $definition['count'],
                    'currency' => 'EUR',
                    'payment_status' => $definition['payment'],
                    'booking_status' => $definition['status'],
                    'notes' => 'Automatisch erzeugte Testbuchung.',
                ]
            );

            $submission->forceFill(['event_booking_id' => $booking->id])->save();

            foreach ($definition['participants'] as $position => $participant) {
                EventBookingParticipant::query()->firstOrCreate(
                    [
                        'event_booking_id' => $booking->id,
                        'position' => $position + 1,
                    ],
                    [
                        'first_name' => $participant[0],
                        'last_name' => $participant[1],
                        'email' => $position === 0 ? $definition['email'] : null,
                        'phone' => $position === 0 ? $definition['phone'] : null,
                        'answers' => ['hinweis' => 'Demo-Buchung für Tenant ' . $tenant->id],
                    ]
                );
            }
        }
    }

    protected function seedEventShifts(int $tenantId, Event $event, $membersById): void
    {
        $shifts = [
            [
                'title' => 'Aufbau',
                'role' => 'Aufbaucrew',
                'starts_at' => $event->start->copy(),
                'ends_at' => $event->start->copy()->addHours(2),
                'required_people' => 4,
                'sort_order' => 1,
                'notes' => 'Pavillons, Garnituren und Technik aufbauen.',
                'assignments' => [
                    ['member_id' => 'DEMO-2001', 'status' => 'confirmed'],
                    ['member_id' => 'DEMO-2003', 'status' => 'confirmed'],
                    ['member_id' => 'DEMO-2013', 'status' => 'confirmed'],
                    ['helper_name' => 'Externer Helfer Max', 'helper_email' => 'max.helfer@example.test', 'status' => 'planned'],
                ],
            ],
            [
                'title' => 'Ausschank',
                'role' => 'Theke',
                'starts_at' => $event->start->copy()->addHours(2),
                'ends_at' => $event->start->copy()->addHours(5),
                'required_people' => 5,
                'sort_order' => 2,
                'notes' => 'Ausschank und Kassenführung.',
                'assignments' => [
                    ['member_id' => 'DEMO-2002', 'status' => 'confirmed'],
                    ['member_id' => 'DEMO-2004', 'status' => 'confirmed'],
                    ['member_id' => 'DEMO-2018', 'status' => 'confirmed'],
                ],
            ],
            [
                'title' => 'Abbau',
                'role' => 'Abbaucrew',
                'starts_at' => $event->end->copy()->subHours(1),
                'ends_at' => $event->end->copy(),
                'required_people' => 3,
                'sort_order' => 3,
                'notes' => 'Aufräumen und Rücktransport.',
                'assignments' => [
                    ['member_id' => 'DEMO-2001', 'status' => 'confirmed'],
                    ['member_id' => 'DEMO-2009', 'status' => 'confirmed'],
                    ['member_id' => 'DEMO-2014', 'status' => 'confirmed'],
                    ['member_id' => 'DEMO-2003', 'status' => 'confirmed'],
                ],
            ],
        ];

        foreach ($shifts as $definition) {
            $shift = EventShift::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'title' => $definition['title'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'role' => $definition['role'],
                    'starts_at' => $definition['starts_at'],
                    'ends_at' => $definition['ends_at'],
                    'required_people' => $definition['required_people'],
                    'sort_order' => $definition['sort_order'],
                    'notes' => $definition['notes'],
                ]
            );

            foreach ($definition['assignments'] as $assignmentDefinition) {
                $member = isset($assignmentDefinition['member_id']) ? ($membersById[$assignmentDefinition['member_id']] ?? null) : null;

                EventShiftAssignment::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'event_shift_id' => $shift->id,
                        'member_id' => $member?->id,
                        'helper_name' => $assignmentDefinition['helper_name'] ?? null,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'event_id' => $event->id,
                        'event_shift_id' => $shift->id,
                        'member_id' => $member?->id,
                        'helper_name' => $assignmentDefinition['helper_name'] ?? null,
                        'helper_email' => $assignmentDefinition['helper_email'] ?? ($member?->email),
                        'helper_phone' => $assignmentDefinition['helper_phone'] ?? ($member?->mobile),
                        'status' => $assignmentDefinition['status'],
                        'notes' => 'Automatisch erzeugte Dienstplan-Zuordnung.',
                    ]
                );
            }
        }
    }

    protected function seedStandaloneForms(int $tenantId): array
    {
        $definitions = [
            [
                'title' => 'Beitrittsanfrage',
                'slug' => 'beitrittsanfrage-demo',
                'description' => 'Interessierte können hier eine Beitrittsanfrage senden.',
                'form_type' => 'membership',
                'success_message' => 'Vielen Dank für euer Interesse.',
                'fields' => [
                    ['label' => 'Name', 'slug' => 'name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 2],
                    ['label' => 'Nachricht', 'slug' => 'nachricht', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 3],
                ],
                'submission' => ['full_name' => 'Saskia Interessiert', 'email' => 'saskia.interessiert@example.test'],
            ],
            [
                'title' => 'Kontaktformular Öffentlichkeit',
                'slug' => 'kontakt-demo',
                'description' => 'Allgemeines Kontaktformular für die Website.',
                'form_type' => 'contact',
                'success_message' => 'Danke, wir melden uns bald zurück.',
                'fields' => [
                    ['label' => 'Name', 'slug' => 'name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 2],
                    ['label' => 'Betreff', 'slug' => 'betreff', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 3],
                ],
                'submission' => ['full_name' => 'Kai Presse', 'email' => 'kai.presse@example.test'],
            ],
        ];

        $forms = [];

        foreach ($definitions as $definition) {
            $form = PublicForm::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $definition['slug']],
                [
                    'tenant_id' => $tenantId,
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'form_type' => $definition['form_type'],
                    'success_message' => $definition['success_message'],
                    'is_active' => true,
                ]
            );

            foreach ($definition['fields'] as $field) {
                PublicFormField::query()->firstOrCreate(
                    ['public_form_id' => $form->id, 'slug' => $field['slug']],
                    $field + ['public_form_id' => $form->id]
                );
            }

            PublicFormSubmission::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'public_form_id' => $form->id, 'email' => $definition['submission']['email']],
                [
                    'tenant_id' => $tenantId,
                    'public_form_id' => $form->id,
                    'full_name' => $definition['submission']['full_name'],
                    'email' => $definition['submission']['email'],
                    'phone' => '01761239999',
                    'answers' => ['quelle' => 'Demo-Website'],
                ]
            );

            $forms[] = $form;
        }

        return $forms;
    }

    protected function seedTemplates(int $tenantId): array
    {
        $definitions = [
            ['name' => 'Willkommensmail Mitglieder', 'type' => Template::TYPE_MAIL, 'subject' => 'Willkommen bei {verein}', 'body' => '<p>Hallo {vorname}, schön, dass du Teil von {verein} bist.</p>'],
            ['name' => 'Einladung Braukurs', 'type' => Template::TYPE_MAIL_AND_LETTER, 'subject' => 'Einladung zum Braukurs', 'body' => '<p>Hallo {vorname}, wir laden dich herzlich zum nächsten Braukurs ein.</p>'],
            ['name' => 'Sponsorenanschreiben', 'type' => Template::TYPE_LETTER, 'subject' => 'Unterstützung für unser Vereinsfest', 'body' => '<p>Sehr geehrte Damen und Herren, wir freuen uns über Ihre Unterstützung.</p>'],
            ['name' => 'Infoblatt PDF', 'type' => Template::TYPE_PDF, 'subject' => 'Info', 'body' => '<p>Dies ist ein internes PDF-Dokument.</p>'],
        ];

        $templates = [];

        foreach ($definitions as $definition) {
            $templates[] = Template::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $definition['name']],
                $definition + ['tenant_id' => $tenantId]
            );
        }

        return $templates;
    }

    protected function seedDispatchLogs(int $tenantId, array $templates, array $members, array $contacts, array $users): void
    {
        $templateByName = collect($templates)->keyBy('name');
        $member = $members[0] ?? null;
        $contact = $contacts[0] ?? null;
        $user = $users[0] ?? null;

        if (! $member || ! $contact || ! $user) {
            return;
        }

        $entries = [
            [
                'template' => 'Willkommensmail Mitglieder',
                'channel' => 'mail',
                'action' => 'sent',
                'recipient_type' => 'member',
                'member_id' => $member->id,
                'recipient_name' => $member->full_name,
                'recipient_reference' => $member->email,
                'subject' => 'Willkommen bei den Bierfreunden',
            ],
            [
                'template' => 'Sponsorenanschreiben',
                'channel' => 'letter',
                'action' => 'generated',
                'recipient_type' => 'contact',
                'contact_id' => $contact->id,
                'recipient_name' => $contact->display_name,
                'recipient_reference' => $contact->email,
                'subject' => 'Unterstützung für unser Vereinsfest',
            ],
        ];

        foreach ($entries as $entry) {
            $template = $templateByName[$entry['template']] ?? null;

            if (! $template) {
                continue;
            }

            TemplateDispatchLog::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'template_id' => $template->id,
                    'channel' => $entry['channel'],
                    'recipient_reference' => $entry['recipient_reference'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'template_id' => $template->id,
                    'created_by' => $user->id,
                    'channel' => $entry['channel'],
                    'action' => $entry['action'],
                    'recipient_type' => $entry['recipient_type'],
                    'member_id' => $entry['member_id'] ?? null,
                    'contact_id' => $entry['contact_id'] ?? null,
                    'recipient_name' => $entry['recipient_name'],
                    'recipient_reference' => $entry['recipient_reference'],
                    'subject' => $entry['subject'],
                    'message_excerpt' => 'Automatisch erzeugter Protokolleintrag für Tenant-Testdaten.',
                    'dispatched_at' => now()->subDays(2),
                    'meta' => ['source' => 'tenant-demo-generator'],
                ]
            );
        }
    }

    protected function seedProjectsAndTasks(int $tenantId, array $users): array
    {
        $owner = $users[0] ?? null;
        $assignee = $users[1] ?? null;

        if (! $owner || ! $assignee) {
            return [];
        }

        $definitions = [
            [
                'name' => 'Maifest Organisation 2026',
                'description' => 'Planung von Ausschank, Aufbau und Kommunikation für das Maifest.',
                'starts_at' => now()->startOfMonth()->toDateString(),
                'ends_at' => now()->addMonth()->endOfMonth()->toDateString(),
                'status' => 'active',
                'tasks' => [
                    ['title' => 'Helferplan finalisieren', 'status' => 'open', 'plan_start' => now()->addDays(1), 'plan_end' => now()->addDays(4), 'percent_done' => 30],
                    ['title' => 'Getränkebestellung bestätigen', 'status' => 'done', 'plan_start' => now()->subDays(6), 'plan_end' => now()->subDays(2), 'percent_done' => 100],
                ],
            ],
            [
                'name' => 'Website & Embeds',
                'description' => 'Öffentliche Eventliste und Formulare für den Verein pflegen.',
                'starts_at' => now()->subWeeks(2)->toDateString(),
                'ends_at' => now()->addWeeks(3)->toDateString(),
                'status' => 'active',
                'tasks' => [
                    ['title' => 'Veranstaltungsliste auf Website testen', 'status' => 'open', 'plan_start' => now()->addDays(2), 'plan_end' => now()->addDays(5), 'percent_done' => 20],
                    ['title' => 'Kontaktformular gegenprüfen', 'status' => 'in_progress', 'plan_start' => now()->subDays(1), 'plan_end' => now()->addDays(1), 'percent_done' => 65],
                ],
            ],
        ];

        $projects = [];

        foreach ($definitions as $definition) {
            $project = Project::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $definition['name']],
                [
                    'tenant_id' => $tenantId,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'starts_at' => $definition['starts_at'],
                    'ends_at' => $definition['ends_at'],
                    'status' => $definition['status'],
                    'owner_id' => $owner->id,
                ]
            );

            foreach ($definition['tasks'] as $taskDefinition) {
                Task::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'project_id' => $project->id, 'title' => $taskDefinition['title']],
                    [
                        'tenant_id' => $tenantId,
                        'project_id' => $project->id,
                        'description' => 'Automatisch erzeugte Projektaufgabe.',
                        'plan_start' => Carbon::parse($taskDefinition['plan_start'])->toDateString(),
                        'plan_end' => Carbon::parse($taskDefinition['plan_end'])->toDateString(),
                        'status' => $taskDefinition['status'],
                        'percent_done' => $taskDefinition['percent_done'],
                        'assignee_id' => $assignee->id,
                        'priority' => 2,
                        'type' => 'task',
                    ]
                );
            }

            $projects[] = $project;
        }

        return $projects;
    }

    protected function seedProtocols(int $tenantId, array $users, array $members): array
    {
        $user = $users[0] ?? null;

        if (! $user) {
            return [];
        }

        $definitions = [
            [
                'title' => 'Vorstandssitzung April 2026',
                'type' => 'vorstand',
                'location' => 'Vereinsheim',
                'start_time' => now()->subDays(14)->setTime(19, 0),
                'end_time' => now()->subDays(14)->setTime(21, 0),
                'content' => 'Themen: Eventplanung, Mitgliederstand, Sponsoring.',
                'resolutions' => 'Maifest wird mit erweitertem Helferplan umgesetzt.',
                'next_meeting' => now()->addDays(18)->toDateString(),
            ],
            [
                'title' => 'Orga-Runde Braukurs',
                'type' => 'orga',
                'location' => 'Sudraum',
                'start_time' => now()->subDays(6)->setTime(18, 30),
                'end_time' => now()->subDays(6)->setTime(20, 0),
                'content' => 'Ablauf, Materialliste und Öffentlichkeitsarbeit abgestimmt.',
                'resolutions' => 'Teilnehmerliste wird bis Freitag final geprüft.',
                'next_meeting' => now()->addDays(7)->toDateString(),
            ],
        ];

        $protocols = [];

        foreach ($definitions as $definition) {
            $protocol = Protocol::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'title' => $definition['title']],
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'title' => $definition['title'],
                    'type' => $definition['type'],
                    'location' => $definition['location'],
                    'start_time' => Carbon::parse($definition['start_time']),
                    'end_time' => Carbon::parse($definition['end_time']),
                    'content' => $definition['content'],
                    'resolutions' => $definition['resolutions'],
                    'next_meeting' => $definition['next_meeting'],
                    'attachments' => [],
                ]
            );

            $protocol->participants()->syncWithoutDetaching(collect($members)->take(4)->pluck('id')->all());
            $protocols[] = $protocol;
        }

        return $protocols;
    }
}
