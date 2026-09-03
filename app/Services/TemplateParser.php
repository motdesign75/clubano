<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Member;
use App\Models\Tenant;
use Carbon\Carbon;

class TemplateParser
{
    public static function parse(string $text, Member|Contact|array $recipient, ?Tenant $tenant = null, array $overrides = []): string
    {
        if ($recipient instanceof Member) {
            $tenant = $tenant ?: Tenant::find($recipient->tenant_id);

            return self::replace($text, array_merge(self::memberVariables($recipient, $tenant), $overrides));
        }

        if ($recipient instanceof Contact) {
            $tenant = $tenant ?: Tenant::find($recipient->tenant_id);

            return self::replace($text, array_merge(self::contactVariables($recipient, $tenant), $overrides));
        }

        $tenant = $tenant ?: Tenant::find($recipient['tenant_id'] ?? null);

        return self::replace($text, array_merge(self::arrayVariables($recipient, $tenant), $overrides));
    }

    private static function replace(string $text, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    private static function memberVariables(Member $member, ?Tenant $tenant): array
    {
        $fullName = trim(implode(' ', array_filter([$member->title, $member->first_name, $member->last_name])));
        $salutation = self::salutation($member->salutation, $fullName);

        return [
            '{anrede}' => $salutation,
            '{name}' => $fullName,
            '{vorname}' => $member->first_name ?? '',
            '{nachname}' => $member->last_name ?? '',
            '{email}' => $member->email ?? '',
            '{telefon}' => $member->mobile ?? $member->landline ?? '',
            '{mitgliedsnummer}' => $member->member_id ?? '',
            '{mitgliedschaft}' => $member->membership?->name ?? '',
            '{austrittsdatum}' => $member->exit_date?->format('d.m.Y') ?? '',
            '{kuendigungsdatum}' => $member->termination_date?->format('d.m.Y') ?? '',
            '{firma}' => $member->organization ?? '',
            '{strasse}' => $member->street ?? '',
            '{plz}' => $member->zip ?? '',
            '{ort}' => $member->city ?? '',
            '{land}' => $member->country ?? '',
            '{verein}' => $tenant->name ?? '',
            '{heute}' => Carbon::now()->format('d.m.Y'),
            '{link}' => '',
        ];
    }

    private static function contactVariables(Contact $contact, ?Tenant $tenant): array
    {
        $fullName = trim(implode(' ', array_filter([$contact->title, $contact->first_name, $contact->last_name])));
        $organization = $contact->organization ?: $contact->company ?: '';
        $displayName = $organization && $fullName ? $organization . ' - ' . $fullName : ($organization ?: $fullName);
        $salutation = self::salutation($contact->salutation, $fullName ?: $organization);

        return [
            '{anrede}' => $salutation,
            '{name}' => $displayName,
            '{vorname}' => $contact->first_name ?? '',
            '{nachname}' => $contact->last_name ?? '',
            '{email}' => $contact->primary_email ?? $contact->email ?? '',
            '{telefon}' => $contact->primary_phone ?? '',
            '{mitgliedsnummer}' => '',
            '{firma}' => $organization,
            '{strasse}' => $contact->street ?? '',
            '{plz}' => $contact->zip ?? '',
            '{ort}' => $contact->city ?? '',
            '{land}' => $contact->country ?? '',
            '{verein}' => $tenant->name ?? '',
            '{heute}' => Carbon::now()->format('d.m.Y'),
            '{link}' => '',
        ];
    }

    private static function arrayVariables(array $recipient, ?Tenant $tenant): array
    {
        $name = trim((string) ($recipient['name'] ?? ''));
        $organization = trim((string) ($recipient['organization'] ?? ''));
        $displayName = $organization && $name ? $organization . ' - ' . $name : ($organization ?: $name);
        $salutation = self::salutation($recipient['salutation'] ?? null, $name ?: $organization);

        $base = [
            '{anrede}' => $salutation,
            '{name}' => $displayName,
            '{vorname}' => (string) ($recipient['first_name'] ?? ''),
            '{nachname}' => (string) ($recipient['last_name'] ?? ''),
            '{email}' => (string) ($recipient['email'] ?? ''),
            '{telefon}' => (string) ($recipient['phone'] ?? ''),
            '{mitgliedsnummer}' => (string) ($recipient['member_id'] ?? ''),
            '{firma}' => $organization,
            '{strasse}' => (string) ($recipient['street'] ?? ''),
            '{plz}' => (string) ($recipient['zip'] ?? ''),
            '{ort}' => (string) ($recipient['city'] ?? ''),
            '{land}' => (string) ($recipient['country'] ?? ''),
            '{verein}' => $tenant->name ?? '',
            '{heute}' => Carbon::now()->format('d.m.Y'),
            '{formular}' => (string) ($recipient['form_title'] ?? ''),
            '{link}' => (string) ($recipient['link'] ?? ($recipient['url'] ?? ($recipient['individual_link'] ?? ''))),
        ];

        $dynamic = collect($recipient)
            ->mapWithKeys(function ($value, $key) {
                if (! is_string($key) || $key === '') {
                    return [];
                }

                $normalizedKey = '{' . trim($key) . '}';

                if (is_bool($value)) {
                    return [$normalizedKey => $value ? 'Ja' : 'Nein'];
                }

                if (is_array($value)) {
                    return [$normalizedKey => implode(', ', array_filter(array_map('strval', $value), fn ($item) => $item !== ''))];
                }

                if (is_scalar($value) || $value === null) {
                    return [$normalizedKey => (string) ($value ?? '')];
                }

                return [];
            })
            ->all();

        return array_merge($base, $dynamic);
    }

    private static function salutation(?string $salutation, string $fallback): string
    {
        $salutation = trim((string) $salutation);

        if ($salutation !== '') {
            return $salutation;
        }

        return $fallback !== '' ? 'Hallo ' . $fallback : 'Guten Tag';
    }
}
