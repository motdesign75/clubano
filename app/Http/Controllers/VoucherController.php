<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\TenantMailConfigurator;
use App\Services\VoucherPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherPdfService $voucherPdfService,
        private readonly TenantMailConfigurator $tenantMailConfigurator,
    ) {
    }

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
            'buyer_email' => ['nullable', 'email', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'dedication_message' => ['nullable', 'string', 'max:500'],
            'delivery_method' => ['required', Rule::in([
                Voucher::DELIVERY_PICKUP,
                Voucher::DELIVERY_MAIL,
                Voucher::DELIVERY_INTERNAL,
            ])],
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
            'buyer_email' => $validated['buyer_email'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'recipient_email' => $validated['recipient_email'] ?? null,
            'dedication_message' => $validated['dedication_message'] ?? null,
            'delivery_method' => $validated['delivery_method'],
            'legacy' => $request->boolean('legacy'),
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('vouchers.index')
            ->with('success', 'Gutschein ' . $voucher->code . ' wurde angelegt.');
    }

    public function settings()
    {
        return view('vouchers.settings', [
            'tenant' => auth()->user()->tenant,
            'positions' => $this->overlayPositions(),
        ]);
    }

    public function check(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $code = Voucher::normalizeCode((string) $request->query('code', ''));
        $voucher = null;

        if ($code !== '') {
            $voucher = Voucher::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->with(['redemptions' => fn ($query) => $query->latest()->limit(5)])
                ->first();
        }

        return view('vouchers.check', [
            'code' => $code,
            'voucher' => $voucher,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'voucher_template' => ['nullable', 'image', 'max:5120'],
            'remove_template' => ['nullable', 'boolean'],
            'voucher_code_position' => ['required', Rule::in(array_keys($this->overlayPositions()))],
            'voucher_code_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'voucher_show_qr' => ['nullable', 'boolean'],
            'voucher_mail_subject' => ['nullable', 'string', 'max:255'],
            'voucher_mail_body' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($request->boolean('remove_template') && $tenant->voucher_template_path) {
            Storage::disk('public')->delete($tenant->voucher_template_path);
            $validated['voucher_template_path'] = null;
            $validated['voucher_template_width'] = null;
            $validated['voucher_template_height'] = null;
        }

        if ($request->hasFile('voucher_template')) {
            if ($tenant->voucher_template_path) {
                Storage::disk('public')->delete($tenant->voucher_template_path);
            }

            $validated['voucher_template_path'] = $request->file('voucher_template')->store('voucher-templates', 'public');
            $dimensions = @getimagesize(Storage::disk('public')->path($validated['voucher_template_path']));
            $validated['voucher_template_width'] = $dimensions[0] ?? null;
            $validated['voucher_template_height'] = $dimensions[1] ?? null;
        }

        unset($validated['voucher_template'], $validated['remove_template']);
        $validated['voucher_show_qr'] = $request->boolean('voucher_show_qr');

        $tenant->update($validated);

        return redirect()
            ->route('vouchers.settings')
            ->with('success', 'Gutscheinvorlage wurde aktualisiert.');
    }

    public function download(Voucher $voucher)
    {
        $this->authorizeVoucher($voucher);

        $pdf = $this->voucherPdfService->render($voucher, auth()->user()->tenant);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->voucherPdfService->filename($voucher) . '"',
        ]);
    }

    public function sendMail(Request $request, Voucher $voucher)
    {
        $this->authorizeVoucher($voucher);
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'dedication_message' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->has('dedication_message')) {
            $voucher->forceFill([
                'dedication_message' => $validated['dedication_message'] ?: null,
            ])->save();
        }

        $this->tenantMailConfigurator->apply($tenant);

        $subject = $tenant->voucher_mail_subject ?: 'Dein Gutschein von ' . $tenant->name;
        $body = $tenant->voucher_mail_body ?: '<p>Guten Tag,</p><p>anbei senden wir den Gutschein <strong>{{ code }}</strong>.</p><p>Viele Grüße<br>{{ verein }}</p>';
        $body = strtr($body, [
            '{{ code }}' => e($voucher->code),
            '{{ wert }}' => e(number_format((float) $voucher->original_amount, 2, ',', '.') . ' ' . strtoupper($voucher->currency ?: 'EUR')),
            '{{ empfaenger }}' => e($voucher->recipient_name ?: ''),
            '{{ widmung }}' => nl2br(e($voucher->dedication_message ?: '')),
            '{{ verein }}' => e($tenant->name),
        ]);

        $pdf = $this->voucherPdfService->render($voucher, $tenant);
        $filename = $this->voucherPdfService->filename($voucher);
        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = filled($tenant->email) && $tenant->email !== $fromAddress ? $tenant->email : null;

        Mail::send('mail.layout', [
            'body' => $body,
            'tenant' => $tenant,
        ], function ($mail) use ($validated, $subject, $fromAddress, $fromName, $replyToAddress, $tenant, $pdf, $filename) {
            $mail->to($validated['email'])
                ->subject($subject)
                ->from($fromAddress, $fromName)
                ->attachData($pdf, $filename, ['mime' => 'application/pdf']);

            if ($replyToAddress) {
                $mail->replyTo($replyToAddress, $tenant->name ?: $fromName);
            }
        });

        $voucher->forceFill([
            'delivery_method' => Voucher::DELIVERY_MAIL,
            'delivered_at' => now(),
        ])->save();

        return redirect()
            ->route('vouchers.index')
            ->with('success', 'Gutschein ' . $voucher->code . ' wurde per Mail versendet.');
    }

    private function authorizeVoucher(Voucher $voucher): void
    {
        abort_unless((int) $voucher->tenant_id === (int) auth()->user()->tenant_id, 404);
    }

    private function overlayPositions(): array
    {
        return [
            'bottom-right' => 'Unten rechts',
            'bottom-left' => 'Unten links',
            'top-right' => 'Oben rechts',
            'top-left' => 'Oben links',
        ];
    }
}
