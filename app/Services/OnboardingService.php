<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Event;
use App\Models\Member;
use App\Models\Membership;
use App\Models\PublicForm;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

class OnboardingService
{
    public function buildForTenant(Tenant $tenant): array
    {
        $profileComplete = filled($tenant->name)
            && filled($tenant->email)
            && filled($tenant->address)
            && filled($tenant->city)
            && filled($tenant->chairman_name);

        $teamCount = $tenant->users()->count();
        $membershipCount = Membership::query()->where('tenant_id', $tenant->id)->count();
        $memberCount = Member::query()->where('tenant_id', $tenant->id)->count();
        $accountCount = Account::query()->where('tenant_id', $tenant->id)->count();
        $eventCount = Event::query()->where('tenant_id', $tenant->id)->count();
        $formCount = PublicForm::query()->where('tenant_id', $tenant->id)->count();

        $steps = [
            $this->makeStep(
                key: 'profile',
                title: 'Vereinsprofil vervollständigen',
                description: 'Name, Kontakt, Vorsitz und Anschrift bilden die Basis für Außenauftritt, PDFs und Formulare.',
                completed: $profileComplete,
                routeName: 'tenant.edit',
                cta: 'Vereinsdaten pflegen',
                meta: $profileComplete ? 'Grunddaten sind gepflegt.' : 'Bitte mindestens Name, E-Mail, Adresse, Ort und Vorsitz ergänzen.',
            ),
            $this->makeStep(
                key: 'team',
                title: 'Team einladen',
                description: 'Lege mindestens einen weiteren Benutzer an, damit Clubano nicht an einem einzigen Login hängt.',
                completed: $teamCount > 1,
                routeName: 'users.create',
                cta: 'Benutzer anlegen',
                meta: $teamCount > 1 ? "{$teamCount} Benutzer im Verein aktiv." : 'Derzeit arbeitet nur ein Benutzer im Verein.',
            ),
            $this->makeStep(
                key: 'memberships',
                title: 'Mitgliedschaften anlegen',
                description: 'Definiere Beitragsmodelle wie Erwachsene, Familie oder Fördermitglied, damit neue Mitglieder sauber starten.',
                completed: $membershipCount > 0,
                routeName: 'memberships.index',
                cta: 'Mitgliedschaften einrichten',
                meta: $membershipCount > 0 ? "{$membershipCount} Mitgliedschaftsmodelle vorhanden." : 'Noch kein Beitragsmodell angelegt.',
            ),
            $this->makeStep(
                key: 'members',
                title: 'Mitglieder übernehmen',
                description: 'Importiere euren Bestand oder lege die ersten Mitglieder manuell an, damit Clubano mit echten Daten arbeitet.',
                completed: $memberCount > 0,
                routeName: $this->preferredMemberRoute(),
                cta: $this->preferredMemberCta(),
                meta: $memberCount > 0 ? "{$memberCount} Mitglieder bereits im Verein." : 'Noch keine Mitglieder im System.',
            ),
            $this->makeStep(
                key: 'accounts',
                title: 'Konten und Kassen einrichten',
                description: 'Lege Vereinskonto, Barkasse und weitere Finanzkonten an, bevor ihr Buchungen oder EÜR sauber nutzen wollt.',
                completed: $accountCount > 0,
                routeName: 'accounts.index',
                cta: 'Konten einrichten',
                meta: $accountCount > 0 ? "{$accountCount} Konten bereits angelegt." : 'Der Finanzbereich hat noch keine Konten.',
            ),
            $this->makeStep(
                key: 'first_use_case',
                title: 'Ersten echten Vereins-Flow starten',
                description: 'Lege ein Event oder ein öffentliches Formular an, damit euer Verein Clubano direkt praktisch testen kann.',
                completed: ($eventCount + $formCount) > 0,
                routeName: $eventCount === 0 ? 'events.create' : 'forms.create',
                cta: $eventCount === 0 ? 'Erstes Event anlegen' : 'Erstes Formular anlegen',
                meta: ($eventCount + $formCount) > 0
                    ? "{$eventCount} Veranstaltungen und {$formCount} Formulare vorhanden."
                    : 'Noch kein Event und noch kein öffentliches Formular angelegt.',
            ),
        ];

        $completedCount = collect($steps)->where('completed', true)->count();
        $totalCount = count($steps);
        $progressPercent = (int) round(($completedCount / max(1, $totalCount)) * 100);
        $nextStep = collect($steps)->firstWhere('completed', false);

        return [
            'steps' => $steps,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'progressPercent' => $progressPercent,
            'nextStep' => $nextStep,
            'isComplete' => $completedCount === $totalCount,
        ];
    }

    private function makeStep(
        string $key,
        string $title,
        string $description,
        bool $completed,
        string $routeName,
        string $cta,
        string $meta,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'completed' => $completed,
            'route' => Route::has($routeName) ? route($routeName) : null,
            'cta' => $cta,
            'meta' => $meta,
        ];
    }

    private function preferredMemberRoute(): string
    {
        return Route::has('import.mitglieder') ? 'import.mitglieder' : 'members.create';
    }

    private function preferredMemberCta(): string
    {
        return Route::has('import.mitglieder') ? 'Mitglieder importieren' : 'Mitglied anlegen';
    }
}
