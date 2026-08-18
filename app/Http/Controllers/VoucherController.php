<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $status = $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

        $vouchers = Voucher::query()
            ->where('tenant_id', $tenantId)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('code', 'like', '%' . Voucher::normalizeCode($search) . '%')
                        ->orWhere('buyer_name', 'like', '%' . $search . '%')
                        ->orWhere('recipient_name', 'like', '%' . $search . '%')
                        ->orWhere('title', 'like', '%' . $search . '%');
                });
            })
            ->withCount('redemptions')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'active_count' => Voucher::query()->where('tenant_id', $tenantId)->where('status', Voucher::STATUS_ACTIVE)->count(),
            'open_value' => Voucher::query()->where('tenant_id', $tenantId)->where('status', Voucher::STATUS_ACTIVE)->sum('remaining_amount'),
            'legacy_count' => Voucher::query()->where('tenant_id', $tenantId)->where('legacy', true)->count(),
        ];

        return view('vouchers.index', compact('vouchers', 'stats', 'status', 'search'));
    }

    public function create()
    {
        return view('vouchers.create', [
            'defaultAmount' => 79,
            'defaultIssuedAt' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('vouchers', 'code')->where('tenant_id', $tenantId),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'original_amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'legacy' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $code = filled($validated['code'] ?? null)
            ? Voucher::normalizeCode($validated['code'])
            : Voucher::nextCode($tenantId);

        if (Voucher::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            return back()
                ->withErrors(['code' => 'Dieser Gutscheincode ist in eurem Verein bereits vergeben.'])
                ->withInput();
        }

        $voucher = Voucher::create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'title' => $validated['title'] ?: 'Gutschein',
            'original_amount' => round((float) $validated['original_amount'], 2),
            'remaining_amount' => round((float) $validated['original_amount'], 2),
            'currency' => 'EUR',
            'issued_at' => $validated['issued_at'] ?? now()->toDateString(),
            'expires_at' => $validated['expires_at'] ?? null,
            'status' => Voucher::STATUS_ACTIVE,
            'buyer_name' => $validated['buyer_name'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'legacy' => $request->boolean('legacy'),
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('vouchers.index')
            ->with('success', 'Gutschein ' . $voucher->code . ' wurde angelegt.');
    }
}
