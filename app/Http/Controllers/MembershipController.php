<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        $memberships = Membership::query()
            ->where('tenant_id', $tenantId)
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $membersWithoutMembership = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->whereNull('membership_id')
            ->whereNull('family_payer_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $billingMembers = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->with(['membership', 'latestMembershipInvoice', 'familyPayer'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Member $member) {
                $lastInvoice = $member->latestMembershipInvoice;
                $nextDate = $member->next_membership_invoice_on
                    ?: $this->suggestNextMembershipInvoiceDate($member, $lastInvoice);

                return [
                    'member' => $member,
                    'membership' => $member->membership,
                    'last_invoice' => $lastInvoice,
                    'next_date' => $nextDate,
                    'is_due' => !$member->family_payer_id && $member->membership_id && $nextDate && $nextDate->lte(now()->startOfDay()),
                    'missing_membership' => blank($member->membership_id),
                ];
            });

        $dueBillingCount = $billingMembers->where('is_due', true)->count();

        return view('memberships.index', compact(
            'memberships',
            'membersWithoutMembership',
            'billingMembers',
            'dueBillingCount'
        ));
    }

    public function create()
    {
        return view('memberships.create');
    }

    public function store(Request $request)
    {
        $this->normalizeAmount($request);
        $this->normalizeDecimalField($request, 'admission_fee');
        $this->normalizeInterval($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'admission_fee' => 'nullable|numeric|min:0',
            'interval' => 'required|in:monatlich,vierteljaehrlich,halbjaehrlich,jaehrlich,vierteljährlich,halbjährlich,jährlich',
        ]);

        $validated['interval'] = $this->canonicalInterval($validated['interval']);
        $validated['tenant_id'] = auth()->user()->tenant_id;

        Membership::create($validated);

        return redirect()->route('memberships.index')->with('success', 'Mitgliedschaft erstellt.');
    }

    public function edit(Membership $membership)
    {
        $this->authorizeTenant($membership);

        return view('memberships.edit', compact('membership'));
    }

    public function update(Request $request, Membership $membership)
    {
        $this->authorizeTenant($membership);

        $this->normalizeAmount($request);
        $this->normalizeDecimalField($request, 'admission_fee');
        $this->normalizeInterval($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'admission_fee' => 'nullable|numeric|min:0',
            'interval' => 'required|in:monatlich,vierteljaehrlich,halbjaehrlich,jaehrlich,vierteljährlich,halbjährlich,jährlich',
        ]);

        $validated['interval'] = $this->canonicalInterval($validated['interval']);
        $membership->update($validated);

        return redirect()->route('memberships.index')->with('success', 'Mitgliedschaft aktualisiert.');
    }

    public function destroy(Membership $membership)
    {
        $this->authorizeTenant($membership);

        $membership->delete();

        return redirect()->route('memberships.index')->with('success', 'Mitgliedschaft gelöscht.');
    }

    public function updateMemberBillingSchedule(Request $request, Member $member)
    {
        if ($member->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unberechtigter Zugriff.');
        }

        $validated = $request->validate([
            'next_membership_invoice_on' => ['nullable', 'date'],
        ]);

        $member->forceFill([
            'next_membership_invoice_on' => $validated['next_membership_invoice_on'] ?? null,
        ])->save();

        return redirect()
            ->route('memberships.index')
            ->with('success', 'Nächster Rechnungstermin wurde gespeichert.');
    }

    public function assignMemberBillingModels(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => [
                'integer',
                Rule::exists('members', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'membership_id' => [
                'required',
                'integer',
                Rule::exists('memberships', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'next_membership_invoice_on' => ['nullable', 'date'],
        ], [
            'member_ids.required' => 'Bitte wähle mindestens ein Mitglied aus.',
            'membership_id.required' => 'Bitte wähle ein Beitragsmodell aus.',
        ]);

        $membership = Membership::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['membership_id']);

        $nextInvoiceOn = $request->filled('next_membership_invoice_on')
            ? $request->date('next_membership_invoice_on')->toDateString()
            : now()->toDateString();

        $updated = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('archived_at')
            ->whereIn('id', $validated['member_ids'])
            ->update([
                'membership_id' => $membership->id,
                'membership_amount' => $membership->amount,
                'membership_interval' => $membership->interval,
                'next_membership_invoice_on' => $nextInvoiceOn,
            ]);

        return redirect()
            ->route('memberships.index')
            ->with('success', $updated . ' Mitglied' . ($updated === 1 ? '' : 'er') . ' wurde' . ($updated === 1 ? '' : 'n') . ' dem Beitragsmodell zugeordnet.');
    }

    /**
     * Schutz vor Zugriff auf fremde Daten.
     */
    private function authorizeTenant(Membership $membership)
    {
        if ($membership->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unberechtigter Zugriff.');
        }
    }

    private function normalizeAmount(Request $request): void
    {
        $this->normalizeDecimalField($request, 'amount');
    }

    private function normalizeDecimalField(Request $request, string $field): void
    {
        $amount = $request->input($field);

        if ($amount === null) {
            return;
        }

        $normalized = trim((string) $amount);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        $request->merge([
            $field => $normalized,
        ]);
    }

    private function normalizeInterval(Request $request): void
    {
        $interval = $request->input('interval');

        if ($interval === null) {
            return;
        }

        $request->merge([
            'interval' => $this->canonicalInterval((string) $interval),
        ]);
    }

    private function canonicalInterval(string $interval): string
    {
        return match (trim(mb_strtolower($interval))) {
            'monatlich' => 'monatlich',
            'vierteljaehrlich', 'vierteljährlich', 'quartal', 'quartalsweise' => 'vierteljährlich',
            'halbjaehrlich', 'halbjährlich', 'halbjahr' => 'halbjährlich',
            'jaehrlich', 'jährlich', 'jahr' => 'jährlich',
            default => trim($interval),
        };
    }

    private function suggestNextMembershipInvoiceDate(Member $member, $lastInvoice): ?Carbon
    {
        if (! $member->membership_id) {
            return null;
        }

        if ($lastInvoice?->period_to) {
            return $lastInvoice->period_to->copy()->addDay()->startOfDay();
        }

        if ($member->entry_date) {
            return $member->entry_date->isFuture()
                ? $member->entry_date->copy()->startOfDay()
                : now()->startOfDay();
        }

        return now()->startOfDay();
    }
}
