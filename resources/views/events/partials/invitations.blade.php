<section class="mt-10 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Einladungen</div>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Wer soll dabei sein?</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                Die Liste entsteht aus der Zielgruppe dieser Aktivität. Jedes Mitglied bekommt einen persönlichen Zu-/Absage-Link.
            </p>
        </div>

        <div class="grid grid-cols-4 gap-2 text-center sm:min-w-[430px]">
            <div class="rounded-xl bg-slate-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ziel</div>
                <div class="mt-1 text-xl font-semibold text-slate-950">{{ $invitationStats['target_members'] }}</div>
            </div>
            <div class="rounded-xl bg-emerald-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Zusagen</div>
                <div class="mt-1 text-xl font-semibold text-emerald-950">{{ $invitationStats['accepted'] }}</div>
            </div>
            <div class="rounded-xl bg-rose-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-rose-700">Absagen</div>
                <div class="mt-1 text-xl font-semibold text-rose-950">{{ $invitationStats['declined'] }}</div>
            </div>
            <div class="rounded-xl bg-amber-50 px-3 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Offen</div>
                <div class="mt-1 text-xl font-semibold text-amber-950">{{ $invitationStats['open'] }}</div>
            </div>
        </div>
    </div>

    <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm leading-6 text-slate-600">
            Zielgruppe: <span class="font-semibold text-slate-950">{{ $event->targetTag?->name ?? 'Alle aktiven Mitglieder' }}</span>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <form method="POST" action="{{ route('events.invitations.sync', $event) }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                    Liste aktualisieren
                </button>
            </form>

            <form method="POST" action="{{ route('events.invitations.mail', $event) }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto">
                    Einladungen per Mail senden
                </button>
            </form>
        </div>
    </div>

    @if($eventInvitations->isNotEmpty())
        <form method="POST" action="{{ route('events.invitations.update', $event) }}" class="mt-5">
            @csrf
            @method('PUT')

            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <div class="hidden grid-cols-[minmax(0,1fr),170px,minmax(220px,0.9fr),minmax(180px,0.8fr)] gap-4 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 lg:grid">
                    <div>Mitglied</div>
                    <div>Rückmeldung</div>
                    <div>Zu-/Absage-Link</div>
                    <div>Notiz</div>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($eventInvitations as $invitation)
                        @php($responseUrl = $invitation->response_token ? route('events.invitations.public.show', $invitation->response_token) : null)
                        <div class="grid gap-4 px-4 py-4 lg:grid-cols-[minmax(0,1fr),170px,minmax(220px,0.9fr),minmax(180px,0.8fr)] lg:items-center">
                            <div class="min-w-0">
                                <input type="hidden" name="invitations[{{ $loop->index }}][id]" value="{{ $invitation->id }}">
                                <div class="font-semibold text-slate-950">{{ $invitation->member->full_name }}</div>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                    @if($invitation->member->email)
                                        <span>{{ $invitation->member->email }}</span>
                                    @endif
                                    @if($invitation->responded_at)
                                        <span>seit {{ $invitation->responded_at->format('d.m.Y H:i') }}</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 lg:hidden">Rückmeldung</label>
                                <select name="invitations[{{ $loop->index }}][status]" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300">
                                    @foreach($invitationStatuses as $status => $label)
                                        <option value="{{ $status }}" @selected($invitation->status === $status)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 lg:hidden">Zu-/Absage-Link</label>
                                @if($responseUrl)
                                    <div class="flex gap-2">
                                        <input type="text" readonly value="{{ $responseUrl }}" class="min-w-0 flex-1 rounded-xl border-slate-300 bg-slate-50 text-xs text-slate-600 focus:border-slate-500 focus:ring-slate-300" onclick="this.select()">
                                        <a href="{{ $responseUrl }}" target="_blank" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-semibold text-white hover:bg-slate-800">
                                            Öffnen
                                        </a>
                                    </div>
                                @else
                                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                        Liste aktualisieren
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 lg:hidden">Notiz</label>
                                <input type="text" name="invitations[{{ $loop->index }}][note]" value="{{ old('invitations.' . $loop->index . '.note', $invitation->note) }}" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-300" placeholder="Optional">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Rückmeldungen speichern
                </button>
            </div>
        </form>
    @else
        <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">
            Noch keine Einladungsliste vorhanden. Klicke auf „Liste aktualisieren“, danach erscheinen hier die persönlichen Zu-/Absage-Links.
        </div>
    @endif
</section>
