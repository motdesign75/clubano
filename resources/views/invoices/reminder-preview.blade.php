@extends('layouts.app')

@section('title', 'Zahlungserinnerung vorbereiten')

@section('content')
    @php
        $previewSubject = old('subject', $subject);
        $previewBody = old('body', $body);
        $reminderLabel = $reminderLevel <= 1 ? 'Zahlungserinnerung' : ($reminderLevel === 2 ? '1. Mahnung' : ($reminderLevel === 3 ? '2. Mahnung' : 'Letzte Mahnung'));
        $logoUrl = $tenant->logo_url
            ? (str_starts_with($tenant->logo_url, 'http://') || str_starts_with($tenant->logo_url, 'https://')
                ? $tenant->logo_url
                : asset($tenant->logo_url))
            : null;
    @endphp

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-5 sm:py-6"
         x-data="reminderPreviewData({
            subject: @js($previewSubject),
            body: @js($previewBody),
            tenant: {
                name: @js($tenant->name),
                email: @js($tenant->email),
                zip: @js($tenant->zip),
                city: @js($tenant->city),
                logoUrl: @js($logoUrl),
            }
         })"
         x-init="refreshPreview()">

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-800">
                <div class="font-semibold">Bitte pruefe deine Eingaben.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-3xl bg-slate-950 px-5 py-5 text-white shadow-sm sm:px-6 sm:py-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Mahnwesen</div>
                    <h1 class="mt-3 text-2xl font-semibold sm:text-3xl">
                        {{ $reminderLabel }} vorbereiten
                    </h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">
                        Erst Empfaenger und Nachricht pruefen, dann bewusst versenden. Der Text bleibt Klartext, die Mail wirkt trotzdem sauber gesetzt.
                    </p>
                </div>

                <a href="{{ route('invoices.show', $invoice) }}"
                   class="inline-flex items-center justify-center rounded-full border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10">
                    Zurueck zur Rechnung
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('invoices.reminder', $invoice) }}" class="space-y-6">
            @csrf

            <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
                <div class="space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">1. Versandrahmen pruefen</h2>
                                <p class="mt-1 text-sm text-slate-500">Damit klar ist, an wen du sendest und in welcher Mahnstufe ihr gerade seid.</p>
                            </div>

                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                {{ $invoice->overdueDays() }} Tag{{ $invoice->overdueDays() === 1 ? '' : 'e' }} ueberfaellig
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Empfaenger</div>
                                <div class="mt-2 font-semibold text-slate-900">{{ $invoice->getRecipientDisplayName() }}</div>
                                <div class="mt-1 text-slate-500">{{ $invoice->recipient_email }}</div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Anhang</div>
                                <div class="mt-2 font-semibold text-slate-900">{{ $invoice->getDocumentLabel() }}_{{ $invoice->invoice_number }}.pdf</div>
                                <div class="mt-1 text-slate-500">Wird automatisch mitgesendet.</div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Rechnung</div>
                                <div class="mt-2 font-semibold text-slate-900">{{ $invoice->invoice_number }}</div>
                                <div class="mt-1 text-slate-500">
                                    Faellig am {{ optional($invoice->due_date)->format('d.m.Y') ?: '—' }}
                                </div>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Mahnstufe</div>
                                <div class="mt-2 font-semibold text-slate-900">{{ $reminderLabel }}</div>
                                <div class="mt-1 text-slate-500">
                                    Bereits versendet: {{ $reminderCount }} {{ $reminderCount === 1 ? 'Mahnstufe' : 'Mahnstufen' }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">2. Nachricht anpassen</h2>
                            <p class="mt-1 text-sm text-slate-500">Du schreibst normalen Text. Clubano setzt ihn automatisch sauber fuer die Mail um.</p>
                        </div>

                        <div class="mt-5 space-y-5">
                            <div>
                                <label for="subject" class="mb-1 block text-sm font-medium text-slate-700">Betreff</label>
                                <input
                                    id="subject"
                                    name="subject"
                                    type="text"
                                    value="{{ $previewSubject }}"
                                    x-model="subject"
                                    @input.debounce.150ms="refreshPreview()"
                                    class="w-full rounded-2xl border-slate-300"
                                >
                            </div>

                            <div>
                                <label for="body" class="mb-1 block text-sm font-medium text-slate-700">Mailtext</label>
                                <textarea
                                    id="body"
                                    name="body"
                                    rows="16"
                                    x-model="body"
                                    @input.debounce.150ms="refreshPreview()"
                                    class="w-full rounded-2xl border-slate-300"
                                >{{ $previewBody }}</textarea>
                                <p class="mt-2 text-sm text-slate-500">
                                    Schreibe mit normalen Absaetzen. Leere Zeilen erzeugen neue Abschnitte in der Mail.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Empfaenger-Vorschau</h2>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <div class="font-semibold text-slate-900">{{ $invoice->getRecipientDisplayName() }}</div>
                            @if($invoice->recipient_company)
                                <div class="mt-1">{{ $invoice->recipient_company }}</div>
                            @endif
                            @if($invoice->recipient_street)
                                <div class="mt-3">{{ $invoice->recipient_street }}</div>
                            @endif
                            <div class="mt-1">
                                {{ $invoice->recipient_zip }} {{ $invoice->recipient_city }}
                            </div>
                            @if($invoice->recipient_country)
                                <div class="mt-1">{{ $invoice->recipient_country }}</div>
                            @endif
                            @if($invoice->recipient_email)
                                <div class="mt-3 text-slate-500">{{ $invoice->recipient_email }}</div>
                            @endif
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">Was soll jetzt passieren?</h2>
                        <p class="mt-2 text-sm text-slate-500">Gleicher Ablauf wie beim Rechnungsversand: erst pruefen, dann bewusst absenden.</p>

                        <div class="mt-5 space-y-3">
                            <button type="submit" class="w-full rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                                {{ $reminderLabel }} jetzt senden
                            </button>
                            <a href="{{ route('invoices.show', $invoice) }}" class="block rounded-full border border-slate-200 px-5 py-3 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                Abbrechen
                            </a>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Vorschau</div>
                                <h2 class="mt-2 text-lg font-semibold text-slate-900">So sieht die Mail aus</h2>
                            </div>
                        </div>

                        <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
                            <iframe
                                x-ref="previewFrame"
                                title="Mail Vorschau"
                                class="h-[880px] w-full rounded-2xl border border-slate-200 bg-white">
                            </iframe>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>

    <script>
        function reminderPreviewData(config) {
            return {
                subject: config.subject || '',
                body: config.body || '',
                tenant: config.tenant || {},

                refreshPreview() {
                    if (!this.$refs.previewFrame) {
                        return;
                    }

                    const html = this.buildMailHtml();
                    this.$refs.previewFrame.srcdoc = html;
                },

                buildMailHtml() {
                    const tenantName = this.escapeHtml(this.tenant.name || '');
                    const tenantEmail = this.escapeHtml(this.tenant.email || '');
                    const tenantZip = this.escapeHtml(this.tenant.zip || '');
                    const tenantCity = this.escapeHtml(this.tenant.city || '');
                    const logoUrl = this.escapeHtml(this.tenant.logoUrl || '');
                    const bodyHtml = this.renderBodyHtml(this.body || '');

                    return `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>${this.escapeHtml(this.subject || '')}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:20px;">
<tr>
<td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:white; border-radius:6px; overflow:hidden;">
<tr>
<td style="background:#2954A3; color:white; padding:15px;">
${logoUrl ? `<img src="${logoUrl}" style="max-height:40px; display:block; margin-bottom:5px;">` : ''}
<strong>${tenantName}</strong>
</td>
</tr>
<tr>
<td style="padding:20px; font-size:14px; color:#333;">
${bodyHtml}
</td>
</tr>
<tr>
<td style="background:#f9fafb; padding:15px; font-size:12px; color:#666;">
Diese Nachricht wurde gesendet von<br>
<strong>${tenantName}</strong><br>
${tenantEmail}<br>
${tenantZip} ${tenantCity}
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>`;
                },

                renderBodyHtml(text) {
                    const normalized = (text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();

                    if (!normalized) {
                        return '';
                    }

                    return normalized
                        .split(/\n{2,}/)
                        .map((paragraph) => `<p>${this.escapeHtml(paragraph.trim()).replace(/\n/g, '<br>')}</p>`)
                        .join('');
                },

                escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
            };
        }
    </script>
@endsection
