<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipBillingController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant->id;
        $today = now()->startOfDay();

        $memberships = Membership::query()
            ->where('tenant_id', $tenantId)
            ->withCount(['members' => fn ($query) => $query->notArchived()])
            ->orderBy('name')
            ->get();

        $billingRows = Member::query()
            ->where('tenant_id', $tenantId)
            ->notArchived()
            ->whereNull('family_payer_id')
            ->with(['membership', 'latestMembershipInvoice'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Member $member) use ($today) {
                $nextDate = $this->nextBillingDateFor($member);

                return [
                    'member' => $member,
                    'membership' => $member->membership,
                    'last_invoice' => $member->latestMembershipInvoice,
                    'next_date' => $nextDate,
                    'is_due' => $member->membership_id && $nextDate && $nextDate->lte($today),
                    'missing_membership' => blank($member->membership_id),
                ];
            });

        $dueRows = $billingRows
            ->where('is_due', true)
            ->sortBy(fn ($row) => optional($row['next_date'])->timestamp ?? 0)
            ->values();

        $missingRows = $billingRows
            ->where('missing_membership', true)
            ->values();

        $missingPaymentRows = $billingRows
            ->filter(fn (array $row) => blank($row['member']->payment_method))
            ->values();

        $openMembershipInvoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'open')
            ->whereNotNull('period_from')
            ->with(['member', 'payments'])
            ->orderBy('due_date')
            ->get();

        $overdueInvoices = $openMembershipInvoices
            ->filter(fn (Invoice $invoice) => $invoice->isOverdue())
            ->values();

        $draftMembershipInvoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'entwurf')
            ->whereNotNull('period_from')
            ->with('member')
            ->latest('invoice_date')
            ->limit(8)
            ->get();

        $stats = [
            'due_members' => $dueRows->count(),
            'missing_membership' => $missingRows->count(),
            'missing_payment_method' => $missingPaymentRows->count(),
            'drafts' => $draftMembershipInvoices->count(),
            'overdue_invoices' => $overdueInvoices->count(),
            'overdue_total' => $overdueInvoices->sum(fn (Invoice $invoice) => $invoice->getRemainingAmount()),
        ];

        return view('membership-billing.index', compact(
            'tenant',
            'memberships',
            'dueRows',
            'missingRows',
            'missingPaymentRows',
            'openMembershipInvoices',
            'overdueInvoices',
            'draftMembershipInvoices',
            'stats',
        ));
    }

    public function updateSettings(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'membership_billing_reminders_enabled' => ['nullable', 'boolean'],
            'dunning_enabled' => ['nullable', 'boolean'],
            'dunning_first_after_days' => ['required', 'integer', 'min:1', 'max:365'],
            'dunning_second_after_days' => ['required', 'integer', 'min:1', 'max:365', 'gte:dunning_first_after_days'],
            'dunning_final_after_days' => ['required', 'integer', 'min:1', 'max:365', 'gte:dunning_second_after_days'],
        ]);

        $tenant->forceFill([
            'membership_billing_reminders_enabled' => $request->boolean('membership_billing_reminders_enabled'),
            'dunning_enabled' => $request->boolean('dunning_enabled'),
            'dunning_first_after_days' => $validated['dunning_first_after_days'],
            'dunning_second_after_days' => $validated['dunning_second_after_days'],
            'dunning_final_after_days' => $validated['dunning_final_after_days'],
        ])->save();

        return redirect()
            ->route('membership-billing.index')
            ->with('success', 'Einstellungen zur Mitgliederabrechnung wurden gespeichert.');
    }

    public function assignMembers(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => [
                'integer',
                Rule::exists('members', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'membership_id' => [
                'nullable',
                'integer',
                Rule::exists('memberships', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'payment_method' => ['nullable', Rule::in(['ueberweisung', 'bar'])],
            'next_membership_invoice_on' => ['nullable', 'date'],
        ], [
            'member_ids.required' => 'Bitte wähle mindestens ein Mitglied aus.',
        ]);

        if (blank($validated['membership_id'] ?? null) && blank($validated['payment_method'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['membership_id' => 'Bitte wähle ein Beitragsmodell oder eine Zahlungsart aus.']);
        }

        $updates = [];

        if (filled($validated['membership_id'] ?? null)) {
            $membership = Membership::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($validated['membership_id']);

            $updates['membership_id'] = $membership->id;
            $updates['membership_amount'] = $membership->amount;
            $updates['membership_interval'] = $membership->interval;
            $updates['next_membership_invoice_on'] = $request->filled('next_membership_invoice_on')
                ? $request->date('next_membership_invoice_on')->toDateString()
                : now()->toDateString();
        }

        if (filled($validated['payment_method'] ?? null)) {
            $updates['payment_method'] = $validated['payment_method'];
        }

        $updated = Member::query()
            ->where('tenant_id', $tenantId)
            ->notArchived()
            ->whereIn('id', $validated['member_ids'])
            ->update($updates);

        return redirect()
            ->route('membership-billing.index')
            ->with('success', $updated . ' Mitglied' . ($updated === 1 ? '' : 'er') . ' wurde' . ($updated === 1 ? '' : 'n') . ' aktualisiert.');
    }

    private function nextBillingDateFor(Member $member): ?CarbonInterface
    {
        if (! $member->membership_id) {
            return null;
        }

        if ($member->next_membership_invoice_on) {
            return $member->next_membership_invoice_on->copy()->startOfDay();
        }

        if ($member->latestMembershipInvoice?->period_to) {
            return $member->latestMembershipInvoice->period_to->copy()->addDay()->startOfDay();
        }

        return $member->entry_date?->isFuture()
            ? $member->entry_date->copy()->startOfDay()
            : now()->startOfDay();
    }
}
