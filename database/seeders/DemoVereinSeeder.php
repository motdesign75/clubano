<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Account;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Protocol;
use App\Models\ProtocolEntry;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DemoVereinSeeder extends Seeder
{
    public const TENANT_SLUG = 'clubano-demo';
    public const USER_EMAIL = 'demo@clubano.demo';
    public const USER_PASSWORD = 'demo2026';

    public function run(): void
    {
        $tenant = Tenant::withoutEvents(function () {
            return Tenant::query()->updateOrCreate(
                ['slug' => self::TENANT_SLUG],
                [
                    'name' => 'Demo-Sportverein Clubano e.V.',
                    'email' => 'verein@clubano.demo',
                    'phone' => '01234 000000',
                    'address' => 'Vereinsweg 1',
                    'zip' => '12345',
                    'city' => 'Demostadt',
                    'chairman_name' => 'Cathrin Vorstand',
                    'license_mode' => 'gifted',
                    'license_expires_at' => null,
                    'is_demo' => true,
                    'donation_certificates_enabled' => true,
                    'donation_certificates_send_enabled' => false,
                    'donation_tax_office' => 'Finanzamt Demostadt',
                    'donation_tax_number' => '12/345/67890',
                    'donation_notice_authority' => 'Finanzamt Demostadt',
                    'donation_notice_date' => now()->subMonths(8)->toDateString(),
                    'donation_notice_valid_until' => now()->addYears(4)->toDateString(),
                    'donation_purposes' => 'Förderung des Sports, der Jugendhilfe und des bürgerschaftlichen Engagements.',
                    'donation_email_body' => "Sehr geehrte Damen und Herren,\n\nvielen Dank für Ihre Spende. Die Zuwendungsbestätigung finden Sie im Anhang.\n\nMit freundlichen Grüßen\nDemo-Sportverein Clubano e.V.",
                ]
            );
        });

        $this->clearTenantData($tenant);

        $user = User::query()->updateOrCreate(
            ['email' => self::USER_EMAIL],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Demo Vorstand',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make(self::USER_PASSWORD),
            ]
        );
        $user->forceFill(['email_verified_at' => now()])->save();

        $membership = Membership::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Aktives Mitglied',
            'amount' => 75,
            'interval' => 'vierteljährlich',
        ]);

        $tags = collect([
            ['name' => 'Vorstand', 'color' => '#2563EB'],
            ['name' => 'Trainerteam', 'color' => '#16A34A'],
            ['name' => 'Helferteam', 'color' => '#F97316'],
        ])->map(fn (array $tag) => Tag::query()->create($tag + ['tenant_id' => $tenant->id]));

        $members = collect(range(1, 200))->map(function (int $index) use ($tenant, $membership) {
            $firstNames = ['Anna', 'Ben', 'Clara', 'Dirk', 'Eva', 'Jonas', 'Mara', 'Nils', 'Lea', 'Tom', 'Sofia', 'Noah', 'Mia', 'Paul', 'Lena', 'Finn', 'Emma', 'Max', 'Marie', 'Luis'];
            $lastNames = ['Schneider', 'Krause', 'Meyer', 'Radecker', 'Sommer', 'Hartmann', 'Wagner', 'Becker', 'Hoffmann', 'Weber', 'Fischer', 'Neumann', 'Scholz', 'Krüger', 'Brandt', 'Keller', 'Vogel', 'Wolf', 'Berger', 'Richter'];
            $firstName = $firstNames[($index - 1) % count($firstNames)];
            $lastName = $lastNames[(int) floor(($index - 1) / count($firstNames)) % count($lastNames)] . ' ' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);

            return Member::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'membership_id' => $membership->id,
                'gender' => 'divers',
                'salutation' => 'Hallo',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => 'mitglied' . str_pad((string) $index, 3, '0', STR_PAD_LEFT) . '@clubano.demo',
                'entry_date' => now()->subYear()->toDateString(),
                'membership_amount' => $membership->amount,
                'membership_interval' => $membership->interval,
                'required_service_hours' => [6, 8, 10, 12][$index % 4],
                'street' => 'Demo Straße ' . (($index % 80) + 1),
                'zip' => '12345',
                'city' => 'Demostadt',
                'country' => 'DE',
                'payment_method' => 'ueberweisung',
                'consent_email' => true,
                'consent_data_processing' => true,
                'consent_given_at' => now()->subMonths(6),
            ]);
        });

        $members->take(12)->each(fn (Member $member) => $member->tags()->attach($tags[0]->id));
        $members->slice(12, 38)->each(fn (Member $member) => $member->tags()->attach($tags[1]->id));
        $members->slice(50, 85)->each(fn (Member $member) => $member->tags()->attach($tags[2]->id));

        $categories = collect([
            ['name' => 'Training', 'slug' => 'training', 'color' => '#16A34A', 'icon' => 'activity'],
            ['name' => 'Sitzung', 'slug' => 'sitzung', 'color' => '#2563EB', 'icon' => 'users'],
            ['name' => 'Arbeitseinsatz', 'slug' => 'arbeitseinsatz', 'color' => '#F97316', 'icon' => 'wrench'],
        ])->map(fn (array $category) => EventCategory::withoutGlobalScopes()->create($category + [
            'tenant_id' => $tenant->id,
            'attendance_enabled_default' => true,
            'response_required_default' => true,
            'counts_toward_required_hours_default' => $category['slug'] === 'arbeitseinsatz',
            'reminders_enabled_default' => true,
        ]));

        $events = collect([
            ['Vorstandssitzung', 'Vereinsheim', 4, 18, 20, $categories[1], $tags[0]],
            ['Training Jugend', 'Sportplatz', 7, 17, 19, $categories[0], $tags[1]],
            ['Sommerfest vorbereiten', 'Vereinsheim', 12, 9, 13, $categories[2], $tags[2]],
        ])->map(function (array $row) use ($tenant, $user) {
            [$title, $location, $days, $startHour, $endHour, $category, $tag] = $row;

            return Event::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'title' => $title,
                'description' => 'Demo-Termin, damit du Kalender, Einladungen und Anwesenheit ausprobieren kannst.',
                'location' => $location,
                'start' => now()->addDays($days)->setTime($startHour, 0),
                'end' => now()->addDays($days)->setTime($endHour, 0),
                'category_id' => $category->id,
                'target_tag_id' => $tag->id,
                'responsible_user_id' => $user->id,
                'is_public' => false,
                'booking_enabled' => false,
                'attendance_enabled' => true,
                'response_required' => true,
                'counts_toward_required_hours' => $category->slug === 'arbeitseinsatz',
                'reminders_enabled' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        $protocol = Protocol::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'title' => 'Demo-Protokoll Vorstandssitzung',
            'type' => 'Vorstandssitzung',
            'location' => 'Vereinsheim',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'raw_agenda' => "TOP 1 Begrüßung\nTOP 2 Sommerfest\nTOP 3 Aufgaben und Termine",
            'raw_notes' => "TOP 2\nBeschluss: Das Sommerfest wird durchgeführt.\nTOP 3\nClara koordiniert die Helferliste bis 15.08.2026.",
            'content' => '<h2>TOP 2 Sommerfest</h2><p>Das Sommerfest wird vorbereitet.</p>',
            'resolutions' => 'Das Sommerfest wird durchgeführt.',
            'next_meeting' => 'Nächste Vorstandssitzung im kommenden Monat.',
        ]);

        $protocol->participants()->sync($members->take(4)->pluck('id')->all());

        collect([
            [ProtocolEntry::TYPE_INFORMATION, 'TOP 1 Begrüßung', 'Anwesenheit festgestellt', 'Der Vorsitz begrüßt die Teilnehmenden.', null, null],
            [ProtocolEntry::TYPE_RESOLUTION, 'TOP 2 Sommerfest', 'Sommerfest durchführen', 'Der Vorstand beschließt, das Sommerfest wie geplant durchzuführen.', null, null],
            [ProtocolEntry::TYPE_TASK, 'TOP 3 Aufgaben und Termine', 'Helferliste koordinieren', 'Clara koordiniert die Helferliste.', 'Clara Meyer', now()->addWeeks(2)->toDateString()],
        ])->each(function (array $entry, int $index) use ($protocol, $tenant) {
            ProtocolEntry::query()->create([
                'tenant_id' => $tenant->id,
                'protocol_id' => $protocol->id,
                'type' => $entry[0],
                'agenda_title' => $entry[1],
                'title' => $entry[2],
                'content' => $entry[3],
                'responsible_name' => $entry[4],
                'due_date' => $entry[5],
                'visible_in_protocol' => true,
                'position' => $index,
            ]);
        });

        Task::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => null,
            'title' => 'Einladungen zum Sommerfest vorbereiten',
            'description' => 'Demo-Aufgabe für den Aufgabenbereich.',
            'plan_end' => now()->addDays(10)->toDateString(),
            'status' => 'open',
            'percent_done' => 25,
            'assignee_id' => $user->id,
            'created_by' => $user->id,
            'priority' => 2,
            'type' => 'task',
        ]);

        Template::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Einladung zur Veranstaltung',
            'subject' => 'Einladung: {{ event.title }}',
            'body' => '<p>Hallo {{ member.first_name }},</p><p>wir laden dich herzlich ein.</p>',
            'type' => Template::TYPE_MAIL_AND_LETTER,
        ]);

        $this->createDemoDocument($tenant, $user, 'Satzung Demo-Sportverein', Document::CATEGORY_CLUB, 'satzung-demo.pdf');
        $this->createDemoDocument($tenant, $user, 'Protokoll Vorstandssitzung', Document::CATEGORY_PROTOCOLS, 'protokoll-demo.pdf', protocolId: $protocol->id);
        $this->createDemoDocument($tenant, $user, 'Sommerfest Ablaufplan', Document::CATEGORY_EVENTS, 'sommerfest-ablauf.pdf', eventId: $events[2]->id);
        $freistellung = $this->createDemoFreistellungDocument($tenant, $user);
        $tenant->forceFill(['donation_freistellung_document_id' => $freistellung->id])->save();

        $this->createFinanceSample($tenant, $user);
        $this->createDonationSample($tenant, $members->all());

        $this->command?->info('Demo-Zugang ist bereit.');
        $this->command?->line('E-Mail: ' . self::USER_EMAIL);
        $this->command?->line('Passwort: ' . self::USER_PASSWORD);
    }

    private function clearTenantData(Tenant $tenant): void
    {
        $tenantId = $tenant->id;

        foreach ([
            'member_tag',
            'protocol_member',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('member_id', Member::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id'))->delete();
            }
        }

        foreach ([
            Document::class,
            Donation::class,
            Template::class,
            ProtocolEntry::class,
            Protocol::class,
            Task::class,
            Transaction::class,
            Account::class,
            Event::class,
            EventCategory::class,
            Member::class,
            Membership::class,
            Tag::class,
        ] as $model) {
            $model::withoutGlobalScopes()->where('tenant_id', $tenantId)->delete();
        }

        User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', '!=', self::USER_EMAIL)
            ->delete();
    }

    private function createDemoDocument(Tenant $tenant, User $user, string $title, string $category, string $fileName, ?int $eventId = null, ?int $protocolId = null): void
    {
        $path = 'documents/' . $tenant->id . '/demo/' . $fileName;
        Storage::disk('local')->put($path, "Clubano Demo-Dokument\n\n{$title}\n");

        Document::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $user->id,
            'title' => $title,
            'category' => $category,
            'status' => Document::STATUS_ACTIVE,
            'description' => 'Beispieldokument für den Demo-Zugang.',
            'tags' => ['Demo', 'Clubano'],
            'document_date' => now()->subDays(14)->toDateString(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $fileName,
            'mime_type' => 'application/pdf',
            'size' => strlen(Storage::disk('local')->get($path)),
            'event_id' => $eventId,
            'protocol_id' => $protocolId,
        ]);
    }

    private function createDemoFreistellungDocument(Tenant $tenant, User $user): Document
    {
        $path = 'documents/' . $tenant->id . '/demo/freistellungsbescheid-demo.pdf';
        Storage::disk('local')->put($path, "Clubano Demo-Dokument\n\nFreistellungsbescheid Demo\n");

        return Document::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $user->id,
            'title' => 'Freistellungsbescheid Demo',
            'category' => Document::CATEGORY_CLUB,
            'status' => Document::STATUS_ACTIVE,
            'description' => 'Demo-Nachweis der Gemeinnützigkeit für Zuwendungsbestätigungen.',
            'tags' => ['Gemeinnützigkeit', 'Freistellungsbescheid', 'Spenden'],
            'document_date' => now()->subMonths(8)->toDateString(),
            'expires_at' => now()->addYears(4)->toDateString(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'freistellungsbescheid-demo.pdf',
            'mime_type' => 'application/pdf',
            'size' => strlen(Storage::disk('local')->get($path)),
        ]);
    }

    private function createFinanceSample(Tenant $tenant, User $user): void
    {
        $accounts = collect([
            'bank' => [
                'number' => '1200',
                'name' => 'Demo Bankkonto',
                'type' => 'bank',
                'tax_area' => 'ideell',
                'iban' => 'DE00123412341234123412',
                'bic' => 'DEMODEMOXXX',
                'balance_start' => 8250,
            ],
            'cash' => [
                'number' => '1000',
                'name' => 'Demo Vereinskasse',
                'type' => 'kasse',
                'tax_area' => 'ideell',
                'balance_start' => 420,
            ],
            'dues' => [
                'number' => '4000',
                'name' => 'Mitgliedsbeiträge',
                'type' => 'einnahme',
                'tax_area' => 'ideell',
                'balance_start' => 0,
            ],
            'donations' => [
                'number' => '4200',
                'name' => 'Spenden',
                'type' => 'einnahme',
                'tax_area' => 'ideell',
                'balance_start' => 0,
            ],
            'events' => [
                'number' => '4300',
                'name' => 'Veranstaltungserlöse',
                'type' => 'einnahme',
                'tax_area' => 'zweckbetrieb',
                'balance_start' => 0,
            ],
            'material' => [
                'number' => '6000',
                'name' => 'Material und Ausstattung',
                'type' => 'ausgabe',
                'tax_area' => 'ideell',
                'balance_start' => 0,
            ],
            'rent' => [
                'number' => '6100',
                'name' => 'Raum und Platzmiete',
                'type' => 'ausgabe',
                'tax_area' => 'ideell',
                'balance_start' => 0,
            ],
        ])->mapWithKeys(function (array $data, string $key) use ($tenant) {
            return [$key => Account::withoutGlobalScopes()->create($data + [
                'tenant_id' => $tenant->id,
                'active' => true,
                'online' => false,
                'balance_date' => now()->startOfYear()->toDateString(),
                'balance_current' => $data['balance_start'],
            ])];
        });

        $transactions = [
            ['Mitgliedsbeiträge Q1 Demo', 15000, 'dues', 'bank', 'ideell', -70],
            ['Mitgliedsbeiträge Q2 Demo', 15000, 'dues', 'bank', 'ideell', -35],
            ['Spende Demo-Förderkreis', 1250, 'donations', 'bank', 'ideell', -24],
            ['Einnahmen Sommerfest', 2180, 'events', 'cash', 'zweckbetrieb', -10],
            ['Einzahlung Sommerfest in Bank', 1500, 'cash', 'bank', 'zweckbetrieb', -8],
            ['Sportmaterial Demo', 890, 'bank', 'material', 'ideell', -18],
            ['Miete Vereinsraum', 650, 'bank', 'rent', 'ideell', -12],
            ['Getränke und Verpflegung', 340, 'cash', 'material', 'zweckbetrieb', -6],
        ];

        foreach ($transactions as $index => [$description, $amount, $from, $to, $taxArea, $days]) {
            Transaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'date' => now()->addDays($days)->toDateString(),
                'description' => $description,
                'amount' => $amount,
                'account_from_id' => $accounts[$from]->id,
                'account_to_id' => $accounts[$to]->id,
                'tax_area' => $taxArea,
                'receipt_number' => 'DEMO-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'status' => 'abgeschlossen',
                'finalized_at' => now()->addDays($days),
                'finalized_by' => $user->id,
            ]);
        }

        $accounts->each(fn (Account $account) => $account->updateBalance());
    }

    private function createDonationSample(Tenant $tenant, array $members): void
    {
        $member = $members[24] ?? null;

        Donation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member?->id,
            'certificate_number' => 'SP-' . now()->year . '-0001',
            'status' => Donation::STATUS_ISSUED,
            'kind' => 'money',
            'donated_at' => now()->subDays(18)->toDateString(),
            'amount' => 250.00,
            'purpose' => 'Jugendarbeit',
            'donor_name' => $member?->full_name ?: 'Max Mustermann',
            'donor_email' => $member?->email ?: 'spender@clubano.demo',
            'donor_street' => $member?->street ?: 'Demoweg 12',
            'donor_zip' => $member?->zip ?: '12345',
            'donor_city' => $member?->city ?: 'Demostadt',
            'payment_method' => 'ueberweisung',
            'certificate_issued_at' => now()->subDays(17),
            'notes' => 'Demo-Spende für die Vorschau der Zuwendungsbestätigung.',
        ]);
    }
}
