<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

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
            'drafts' => $draftMembershipInvoices->count(),
            'overdue_invoices' => $overdueInvoices->count(),
            'overdue_total' => $overdueInvoices->sum(fn (Invoice $invoice) => $invoice->getRemainingAmount()),
        ];

        return view('membership-billing.index', compact(
            'tenant',
            'memberships',
            'dueRows',
            'missingRows',
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
