<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventBookingParticipant;
use App\Models\Member;
use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\PublicFormSubmission;
use App\Models\TemplateDispatchLog;
use App\Services\EventBookingBillingService;
use App\Services\TenantMailConfigurator;
use App\Services\TemplateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rule;

class PublicFormController extends Controller
{
    public function __construct(
        private readonly EventBookingBillingService $eventBookingBillingService,
        private readonly TenantMailConfigurator $tenantMailConfigurator,
    ) {
    }

    public function index()
    {
        $forms = PublicForm::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->withCount(['fields', 'submissions'])
            ->orderBy('title')
            ->get();

        return view('forms.index', compact('forms'));
    }

    public function create()
    {
        return view('forms.create', $this->formViewData(new PublicForm([
            'form_type' => 'general',
            'is_active' => true,
        ])));
    }

    public function store(Request $request)
    {
        $validated = $this->validateForm($request);
        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title']);
        $validated['success_message'] = $validated['success_message'] ?: 'Vielen Dank. Das Formular wurde erfolgreich gesendet.';
        $validated = $this->normalizeConfirmationMail($validated);

        $form = PublicForm::create($validated);
        $this->seedStarterFields($form);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Formular wurde angelegt. Ein passendes Starter-Set an Feldern ist bereits vorbereitet.');
    }

    public function edit(PublicForm $form)
    {
        $this->authorizeForm($form);
        $form->load('fields');

        return view('forms.edit', $this->formViewData($form));
    }

    public function update(Request $request, PublicForm $form)
    {
        $this->authorizeForm($form);

        $validated = $this->validateForm($request, $form);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title'], $form->id);
        $validated['success_message'] = $validated['success_message'] ?: 'Vielen Dank. Das Formular wurde erfolgreich gesendet.';
        $validated = $this->normalizeConfirmationMail($validated);

        $form->update($validated);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Formular wurde aktualisiert.');
    }

    public function destroy(PublicForm $form)
    {
        $this->authorizeForm($form);
        $form->delete();

        return redirect()
            ->route('forms.index')
            ->with('success', 'Formular wurde gelöscht.');
    }

    public function submissions(PublicForm $form)
    {
        $this->authorizeForm($form);
        $form->load('event');

        $submissions = $form->submissions()
            ->with(['eventBooking.participants', 'member', 'contact'])
            ->paginate(20);

        $manualParticipantMembers = collect();
        $manualParticipantContacts = collect();

        if (auth()->user()?->canManageForms()) {
            $manualParticipantMembers = Member::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->whereNull('archived_at')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'organization', 'email', 'mobile', 'landline']);

            $manualParticipantContacts = Contact::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('is_active', true)
                ->orderBy('organization')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'organization', 'company', 'first_name', 'last_name', 'email', 'mobile', 'phone', 'phone_mobile', 'phone_landline']);
        }

        return view('forms.submissions', compact('form', 'submissions', 'manualParticipantMembers', 'manualParticipantContacts'));
    }

    public function export(PublicForm $form)
    {
        $this->authorizeForm($form);
        $form->load('fields');

        $submissions = $form->submissions()
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="formular-' . $form->slug . '-antworten.csv"',
        ];

        $columns = ['id', 'eingegangen_am', 'full_name', 'email', 'phone'];
        foreach ($form->fields as $field) {
            $columns[] = $field->slug;
        }

        $callback = function () use ($columns, $submissions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns, ';');

            foreach ($submissions as $submission) {
                $row = [
                    $submission->id,
                    $submission->created_at->format('Y-m-d H:i:s'),
                    $submission->full_name,
                    $submission->email,
                    $submission->phone,
                ];

                foreach (array_slice($columns, 5) as $fieldSlug) {
                    $value = $submission->answers[$fieldSlug] ?? null;

                    if (is_bool($value)) {
                        $value = $value ? 'Ja' : 'Nein';
                    } elseif (is_array($value)) {
                        $value = implode(', ', $value);
                    }

                    $row[] = $value;
                }

                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function cancelSubmission(PublicForm $form, PublicFormSubmission $submission)
    {
        $this->authorizeForm($form);
        $this->authorizeSubmission($form, $submission);

        if ($submission->status === 'cancelled') {
            return redirect()
                ->route('forms.submissions', $form)
                ->with('success', 'Die Antwort ist bereits storniert.');
        }

        DB::transaction(function () use ($submission) {
            $submission->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $booking = $submission->eventBooking;

            if ($booking) {
                $booking->update([
                    'booking_status' => 'cancelled',
                    'payment_status' => $booking->payment_status === 'open' ? 'cancelled' : $booking->payment_status,
                ]);
            }
        });

        return redirect()
            ->route('forms.submissions', $form)
            ->with('success', 'Die Antwort wurde storniert.');
    }

    public function destroySubmission(PublicForm $form, PublicFormSubmission $submission)
    {
        $this->authorizeForm($form);
        $this->authorizeSubmission($form, $submission);

        DB::transaction(function () use ($submission) {
            $booking = $submission->eventBooking;

            if ($booking) {
                $booking->participants()->delete();
                $booking->delete();
            }

            $submission->delete();
        });

        return redirect()
            ->route('forms.submissions', $form)
            ->with('success', 'Die Antwort wurde gelöscht.');
    }

    public function convertSubmissionToMember(Request $request, PublicForm $form, PublicFormSubmission $submission)
    {
        $this->authorizeForm($form);
        $this->authorizeSubmission($form, $submission);

        $validated = $request->validate([
            'confirmed' => ['accepted'],
            'entry_date' => ['nullable', 'date'],
        ], [
            'confirmed.accepted' => 'Bitte bestätige, dass die Antwort geprüft wurde.',
        ]);

        if ($submission->member_id) {
            return redirect()
                ->route('forms.submissions', $form)
                ->with('success', 'Diese Antwort ist bereits mit einem Mitglied verknüpft.');
        }

        $answers = $submission->answers ?? [];
        $payload = $this->memberPayloadFromSubmission($submission, $answers, $validated['entry_date'] ?? null);

        if (! $this->hasPersonOrOrganization($payload)) {
            return redirect()
                ->route('forms.submissions', $form)
                ->withErrors(['conversion' => 'Für ein Mitglied fehlen Name, Organisation oder E-Mail. Bitte prüfe die Antwort.']);
        }

        if ($duplicate = $this->findDuplicateMember($payload, (int) $form->tenant_id)) {
            $submission->update(['member_id' => $duplicate['record']->id]);

            return redirect()
                ->route('forms.submissions', $form)
                ->with('success', 'Mögliche Dublette erkannt. Die Antwort wurde mit dem vorhandenen Mitglied verknüpft: ' . ($duplicate['record']->full_name ?: $duplicate['record']->organization ?: $duplicate['record']->email) . ' (' . $duplicate['reason'] . ').');
        }

        $member = DB::transaction(function () use ($submission, $payload) {
            $member = Member::create($payload);
            $submission->update(['member_id' => $member->id]);

            return $member;
        });

        return redirect()
            ->route('forms.submissions', $form)
            ->with('success', 'Antwort wurde als Mitglied übernommen: ' . ($member->full_name ?: $member->organization ?: $member->email));
    }

    public function convertSubmissionToContact(Request $request, PublicForm $form, PublicFormSubmission $submission)
    {
        $this->authorizeForm($form);
        $this->authorizeSubmission($form, $submission);

        $request->validate([
            'confirmed' => ['accepted'],
        ], [
            'confirmed.accepted' => 'Bitte bestätige, dass die Antwort geprüft wurde.',
        ]);

        if ($submission->contact_id) {
            return redirect()
                ->route('forms.submissions', $form)
                ->with('success', 'Diese Antwort ist bereits mit einem Kontakt verknüpft.');
        }

        $answers = $submission->answers ?? [];
        $payload = $this->contactPayloadFromSubmission($submission, $answers);

        if (! $this->hasPersonOrOrganization($payload)) {
            return redirect()
                ->route('forms.submissions', $form)
                ->withErrors(['conversion' => 'Für einen Kontakt fehlen Name, Organisation oder E-Mail. Bitte prüfe die Antwort.']);
        }

        if ($duplicate = $this->findDuplicateContact($payload, (int) $form->tenant_id)) {
            $submission->update(['contact_id' => $duplicate['record']->id]);

            return redirect()
                ->route('forms.submissions', $form)
                ->with('success', 'Mögliche Dublette erkannt. Die Antwort wurde mit dem vorhandenen Kontakt verknüpft: ' . $duplicate['record']->display_name . ' (' . $duplicate['reason'] . ').');
        }

        $contact = DB::transaction(function () use ($submission, $payload) {
            $contact = Contact::create($payload);
            $submission->update(['contact_id' => $contact->id]);

            return $contact;
        });

        return redirect()
            ->route('forms.submissions', $form)
            ->with('success', 'Antwort wurde als Kontakt übernommen: ' . $contact->display_name);
    }

    public function convertSubmissionToEventParticipant(Request $request, PublicForm $form, PublicFormSubmission $submission)
    {
        $this->authorizeForm($form);
        $this->authorizeSubmission($form, $submission);

        $event = $submission->event ?: $form->event;

        abort_unless($event && (int) $event->tenant_id === (int) $form->tenant_id, 404);

        $validated = $request->validate([
            'confirmed' => ['accepted'],
            'participant_type' => ['required', Rule::in(['guest', 'member', 'contact'])],
            'member_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('participant_type') === 'member'),
                Rule::exists('members', 'id')->where('tenant_id', $form->tenant_id),
            ],
            'contact_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('participant_type') === 'contact'),
                Rule::exists('contacts', 'id')->where('tenant_id', $form->tenant_id),
            ],
            'payment_required' => ['nullable', 'boolean'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'payment_status' => ['required', Rule::in(['not_required', 'open', 'paid', 'cancelled'])],
            'payment_reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'confirmed.accepted' => 'Bitte bestätige, dass die Antwort geprüft wurde.',
            'member_id.required' => 'Bitte wähle ein Mitglied aus.',
            'contact_id.required' => 'Bitte wähle einen Kontakt aus.',
        ]);

        if ($submission->event_booking_id) {
            return redirect()
                ->route('forms.submissions', $form)
                ->with('success', 'Diese Antwort ist bereits mit einer Event-Buchung verknüpft.');
        }

        $answers = $submission->answers ?? [];
        $participantPayload = $this->participantPayloadFromSubmission($submission, $answers, $validated);

        if (! $this->hasPersonOrOrganization($participantPayload)) {
            return redirect()
                ->route('forms.submissions', $form)
                ->withErrors(['conversion' => 'Für einen Teilnehmer fehlen Name, Organisation oder E-Mail. Bitte prüfe die Antwort.']);
        }

        $paymentRequired = $request->boolean('payment_required');
        $priceAmount = $paymentRequired ? round((float) ($validated['price_amount'] ?? $event->price_per_person ?? 0), 2) : 0;
        $paymentStatus = $paymentRequired ? $validated['payment_status'] : 'not_required';

        if ($priceAmount <= 0 && $paymentStatus === 'open') {
            $paymentStatus = 'not_required';
        }

        $booking = DB::transaction(function () use ($submission, $event, $participantPayload, $validated, $paymentRequired, $priceAmount, $paymentStatus) {
            $booking = EventBooking::create([
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'public_form_submission_id' => $submission->id,
                'booking_reference' => $this->generateBookingReference($event),
                'booker_name' => $participantPayload['organization_name'] ?: trim(($participantPayload['first_name'] ?? '') . ' ' . ($participantPayload['last_name'] ?? '')) ?: ($submission->full_name ?: 'Formularantwort'),
                'booker_email' => $participantPayload['email'] ?? $submission->email,
                'booker_phone' => $participantPayload['phone'] ?? $submission->phone,
                'participant_count' => 1,
                'price_per_person' => $priceAmount,
                'total_amount' => $priceAmount,
                'currency' => strtoupper($event->currency ?: 'EUR'),
                'payment_status' => $paymentStatus,
                'booking_status' => 'confirmed',
                'notes' => trim('Aus Formularantwort übernommen' . (filled($validated['note'] ?? null) ? ': ' . $validated['note'] : '')),
            ]);

            $booking->participants()->create(array_merge($participantPayload, [
                'position' => 1,
                'participant_type' => $validated['participant_type'],
                'payment_required' => $paymentRequired,
                'price_amount' => $priceAmount,
                'payment_status' => $paymentStatus,
                'payment_reason' => $validated['payment_reason'] ?? null,
                'source' => 'manual',
                'note' => $validated['note'] ?? null,
                'answers' => $submission->answers ?? [],
            ]));

            $submission->update(['event_booking_id' => $booking->id]);

            return $booking;
        });

        return redirect()
            ->route('forms.submissions', $form)
            ->with('success', 'Antwort wurde als Teilnehmer übernommen: ' . $booking->booker_name);
    }

    public function storeField(Request $request, PublicForm $form)
    {
        $this->authorizeForm($form);

        $validated = $this->validateField($request, $form);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['label'], '_');
        $validated['sort_order'] = ($form->fields()->max('sort_order') ?? 0) + 1;
        $validated = $this->normalizeFieldPayload($validated);

        $form->fields()->create($validated);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Feld wurde hinzugefügt.');
    }

    public function updateField(Request $request, PublicForm $form, PublicFormField $field)
    {
        $this->authorizeForm($form);
        $this->authorizeField($form, $field);

        $validated = $this->validateField($request, $form, $field);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['label'], '_');
        $validated = $this->normalizeFieldPayload($validated);

        $field->update($validated);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Feld wurde aktualisiert.');
    }

    public function destroyField(PublicForm $form, PublicFormField $field)
    {
        $this->authorizeForm($form);
        $this->authorizeField($form, $field);

        $field->delete();
        $this->normalizeSortOrder($form);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Feld wurde gelöscht.');
    }

    public function moveField(Request $request, PublicForm $form, PublicFormField $field)
    {
        $this->authorizeForm($form);
        $this->authorizeField($form, $field);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $fields = $form->fields()->orderBy('sort_order')->get()->values();
        $currentIndex = $fields->search(fn (PublicFormField $item) => $item->id === $field->id);

        if ($currentIndex === false) {
            abort(404);
        }

        $swapIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if (!isset($fields[$swapIndex])) {
            return redirect()
                ->route('forms.edit', $form)
                ->with('success', 'Die Reihenfolge ist bereits optimal.');
        }

        $swapField = $fields[$swapIndex];
        $currentOrder = $field->sort_order;

        $field->update(['sort_order' => $swapField->sort_order]);
        $swapField->update(['sort_order' => $currentOrder]);

        $this->normalizeSortOrder($form);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Die Reihenfolge der Felder wurde aktualisiert.');
    }

    public function publicShow(string $slug)
    {
        $form = $this->resolvePublicForm($slug);
        $this->ensureEventBillingFields($form);

        return view('forms.public', compact('form'));
    }

    public function publicEmbed(string $slug)
    {
        $form = $this->resolvePublicForm($slug);
        $this->ensureEventBillingFields($form);

        return response()
            ->view('forms.embed', compact('form'))
            ->header('Content-Security-Policy', "frame-ancestors *")
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function publicSubmit(Request $request, string $slug)
    {
        return $this->handlePublicSubmit($request, $slug, false);
    }

    public function publicEmbedSubmit(Request $request, string $slug)
    {
        return $this->handlePublicSubmit($request, $slug, true);
    }

    private function handlePublicSubmit(Request $request, string $slug, bool $embedded)
    {
        $form = $this->resolvePublicForm($slug);
        $this->ensureEventBillingFields($form);

        $rules = [];
        $isEventBooking = $form->form_type === 'event' && $form->event;
        $maxParticipants = max(1, (int) ($form->event?->max_participants_per_booking ?: 1));
        $fieldSlugs = $form->fields->pluck('slug');

        foreach ($form->fields as $field) {
            if ($isEventBooking && in_array($field->slug, ['participant_count', 'participant_notes'], true)) {
                continue;
            }

            if ($isEventBooking && $field->isLegacyEventBookingAddressDuplicate($fieldSlugs)) {
                continue;
            }

            $key = 'fields.' . $field->slug;
            $options = $this->parseOptions($field->options);
            $fieldRules = match ($field->field_type) {
                'email' => ['nullable', 'email'],
                'number' => ['nullable', 'numeric'],
                'date' => ['nullable', 'date'],
                'checkbox' => ['nullable', 'boolean'],
                'select', 'radio' => $options !== []
                    ? ['nullable', 'string', ValidationRule::in($options)]
                    : ['nullable', 'string'],
                'checkbox_group' => $options !== []
                    ? ['nullable', 'array']
                    : ['nullable', 'array'],
                default => ['nullable', 'string'],
            };

            if ($field->is_required) {
                $fieldRules = array_values(array_filter($fieldRules, fn ($rule) => $rule !== 'nullable'));
                if ($field->field_type === 'checkbox') {
                    array_unshift($fieldRules, 'accepted');
                } elseif ($field->field_type === 'checkbox_group') {
                    array_unshift($fieldRules, 'required', 'min:1');
                } else {
                    array_unshift($fieldRules, 'required');
                }
            }

            $rules[$key] = $fieldRules;

            if ($field->field_type === 'checkbox_group' && $options !== []) {
                $rules[$key . '.*'] = ['string', ValidationRule::in($options)];
            }
        }

        if ($isEventBooking) {
            $rules['participant_count'] = ['required', 'integer', 'min:1', 'max:' . $maxParticipants];
            $rules['participant_notes'] = ['nullable', 'string'];
            $rules['use_booker_as_participant'] = ['nullable', 'boolean'];
            $rules['participants'] = ['nullable', 'array', 'min:1', 'max:' . $maxParticipants];
            $rules['participants.*.first_name'] = ['nullable', 'string', 'max:255'];
            $rules['participants.*.last_name'] = ['nullable', 'string', 'max:255'];
            $rules['participants.*.email'] = ['nullable', 'email'];
            $rules['participants.*.phone'] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);
        $answers = collect($form->fields)
            ->reject(fn (PublicFormField $field) => $isEventBooking && $field->isLegacyEventBookingAddressDuplicate($fieldSlugs))
            ->mapWithKeys(function (PublicFormField $field) use ($validated) {
            $value = data_get($validated, 'fields.' . $field->slug);

            if ($field->field_type === 'checkbox') {
                $value = (bool) $value;
            } elseif ($field->field_type === 'checkbox_group') {
                $value = collect($value ?? [])
                    ->filter(fn ($item) => filled($item))
                    ->values()
                    ->all();
            }

            return [$field->slug => $value];
        })->all();

        if ($isEventBooking) {
            $answers['participant_count'] = (int) ($validated['participant_count'] ?? 1);
            $answers['participant_notes'] = $validated['participant_notes'] ?? null;
            $answers['use_booker_as_participant'] = (bool) ($validated['use_booker_as_participant'] ?? false);
        }

        $submission = DB::transaction(function () use ($form, $answers, $validated, $isEventBooking) {
            $submission = PublicFormSubmission::create([
                'public_form_id' => $form->id,
                'tenant_id' => $form->tenant_id,
                'event_id' => $form->event_id,
                'full_name' => trim(($answers['first_name'] ?? '') . ' ' . ($answers['last_name'] ?? '')) ?: ($answers['full_name'] ?? null),
                'email' => $answers['email'] ?? null,
                'phone' => $answers['mobile'] ?? ($answers['phone'] ?? null),
                'answers' => $answers,
            ]);

            if ($isEventBooking && $form->event) {
                $useBookerAsParticipant = (bool) ($answers['use_booker_as_participant'] ?? false);
                $participantTarget = (int) ($answers['participant_count'] ?? 1);
                $additionalParticipantTarget = max(0, $participantTarget - ($useBookerAsParticipant ? 1 : 0));
                $participantRows = collect($validated['participants'] ?? [])
                    ->take($additionalParticipantTarget)
                    ->values();

                if ($useBookerAsParticipant) {
                    $participantRows = collect([[
                        'first_name' => trim((string) ($answers['first_name'] ?? '')),
                        'last_name' => trim((string) ($answers['last_name'] ?? '')),
                        'email' => $answers['email'] ?? null,
                        'phone' => $answers['mobile'] ?? ($answers['phone'] ?? null),
                    ]])->concat($participantRows)->values();
                }

                $participantRows = $participantRows
                    ->map(function ($participant) {
                        return [
                            'first_name' => trim((string) ($participant['first_name'] ?? '')),
                            'last_name' => trim((string) ($participant['last_name'] ?? '')),
                            'email' => filled($participant['email'] ?? null) ? trim((string) $participant['email']) : null,
                            'phone' => filled($participant['phone'] ?? null) ? trim((string) $participant['phone']) : null,
                        ];
                    })
                    ->filter(fn ($participant) => $participant['first_name'] !== '' || $participant['last_name'] !== '')
                    ->values();

                if ($participantRows->count() !== $participantTarget) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'participants' => $participantTarget === 1
                            ? 'Bitte trage einen Teilnehmer ein oder nutze den Ansprechpartner als Teilnehmer.'
                            : 'Bitte trage alle Teilnehmer vollständig ein.',
                    ]);
                }

                $participantCount = max(1, $participantRows->count());
                $pricePerPerson = (float) ($form->event->price_per_person ?? 0);
                $totalAmount = $participantCount * $pricePerPerson;

                $booking = EventBooking::create([
                    'tenant_id' => $form->tenant_id,
                    'event_id' => $form->event_id,
                    'public_form_submission_id' => $submission->id,
                    'booking_reference' => $this->generateBookingReference($form->event),
                    'booker_name' => trim(($answers['first_name'] ?? '') . ' ' . ($answers['last_name'] ?? '')),
                    'booker_email' => $answers['email'] ?? null,
                    'booker_phone' => $answers['mobile'] ?? ($answers['phone'] ?? null),
                    'participant_count' => $participantCount,
                    'price_per_person' => $pricePerPerson,
                    'total_amount' => $totalAmount,
                    'currency' => strtoupper($form->event->currency ?: 'EUR'),
                    'payment_status' => $pricePerPerson > 0 ? 'open' : 'not_required',
                    'booking_status' => 'pending',
                    'notes' => $answers['participant_notes'] ?? null,
                ]);

                foreach ($participantRows as $index => $participant) {
                    EventBookingParticipant::create([
                        'event_booking_id' => $booking->id,
                        'position' => $index + 1,
                        'first_name' => $participant['first_name'],
                        'last_name' => $participant['last_name'],
                        'email' => $participant['email'] ?? null,
                        'phone' => $participant['phone'] ?? null,
                        'participant_type' => 'guest',
                        'payment_required' => $pricePerPerson > 0,
                        'price_amount' => $pricePerPerson,
                        'payment_status' => $pricePerPerson > 0 ? 'open' : 'not_required',
                        'source' => 'online',
                        'answers' => [],
                    ]);
                }

                $submission->update([
                    'event_booking_id' => $booking->id,
                ]);

                if ($pricePerPerson > 0 && $form->tenant) {
                    $invoice = $this->eventBookingBillingService->createInvoiceForBooking(
                        $booking,
                        $answers,
                        $form->event,
                        $form->tenant
                    );

                    $booking->setRelation('invoice', $invoice);
                }
            }

            return $submission->fresh(['member', 'contact', 'eventBooking.invoice']);
        });

        if ($submission->eventBooking && $submission->eventBooking->invoice && $form->event && $form->tenant) {
            $this->eventBookingBillingService->sendInvoiceMail(
                $submission->eventBooking->invoice,
                $submission->eventBooking,
                $form->event,
                $form->tenant
            );
        }

        $this->sendConfirmationMail($form, $submission, $answers);

        return redirect()
            ->route($embedded ? 'forms.public.embed' : 'forms.public.show', $form->slug)
            ->with('success', $form->success_message ?: 'Vielen Dank. Das Formular wurde erfolgreich gesendet.');
    }

    private function formViewData(PublicForm $form): array
    {
        return [
            'form' => $form,
            'events' => Event::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->orderBy('start')
                ->get(),
            'formTypes' => [
                'general' => 'Allgemeines Formular',
                'contact' => 'Kontaktformular',
                'membership' => 'Beitrittsformular',
                'event' => 'Event-Anmeldung',
            ],
            'fieldTypes' => [
                'text' => 'Text',
                'email' => 'E-Mail',
                'number' => 'Zahl',
                'date' => 'Datum',
                'textarea' => 'Mehrzeilig',
                'select' => 'Dropdown-Auswahl',
                'radio' => 'Einzelauswahl',
                'checkbox_group' => 'Mehrfachauswahl',
                'checkbox' => 'Zustimmungs-Checkbox',
            ],
        ];
    }

    private function validateForm(Request $request, ?PublicForm $form = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'description' => ['nullable', 'string'],
            'form_type' => ['required', Rule::in(['general', 'contact', 'membership', 'event'])],
            'success_message' => ['nullable', 'string'],
            'confirmation_mail_enabled' => ['nullable', 'boolean'],
            'confirmation_mail_subject' => ['nullable', 'string', 'max:255'],
            'confirmation_mail_body' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'event_id' => [
                'nullable',
                Rule::exists('events', 'id')->where('tenant_id', auth()->user()->tenant_id),
            ],
        ]) + [
            'confirmation_mail_enabled' => $request->boolean('confirmation_mail_enabled'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function validateField(Request $request, PublicForm $form, ?PublicFormField $field = null): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('public_form_fields', 'slug')
                    ->where('public_form_id', $form->id)
                    ->ignore($field?->id),
            ],
            'field_type' => ['required', Rule::in(['text', 'email', 'number', 'date', 'textarea', 'select', 'radio', 'checkbox_group', 'checkbox'])],
            'help_text' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
        ]) + [
            'is_required' => $request->boolean('is_required'),
        ];

        if (in_array($validated['field_type'], ['select', 'radio', 'checkbox_group'], true)
            && blank($this->normalizeOptions($validated['options'] ?? null))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'options' => 'Bitte lege fuer dieses Auswahlfeld mindestens eine Option an.',
            ]);
        }

        return $validated;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $counter = 1;

        while (
            PublicForm::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function normalizeFieldPayload(array $validated): array
    {
        $validated['options'] = $this->normalizeOptions($validated['options'] ?? null);

        if (!in_array($validated['field_type'], ['select', 'radio', 'checkbox_group'], true)) {
            $validated['options'] = null;
        }

        if ($validated['field_type'] === 'checkbox' && blank($validated['help_text'] ?? null)) {
            $validated['help_text'] = 'Ich stimme zu.';
        }

        return $validated;
    }

    private function normalizeOptions(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $parts = preg_split('/\r\n|\r|\n|\|/', $value) ?: [];
        $options = collect($parts)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();

        return $options->isEmpty() ? null : $options->implode('|');
    }

    private function parseOptions(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        return collect(preg_split('/\|/', (string) $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function memberPayloadFromSubmission(PublicFormSubmission $submission, array $answers, ?string $entryDate = null): array
    {
        return [
            'tenant_id' => $submission->tenant_id,
            'organization' => $this->answer($answers, 'organization'),
            'first_name' => $this->answer($answers, 'first_name') ?: $this->firstNameFromFullName($submission->full_name),
            'last_name' => $this->answer($answers, 'last_name') ?: $this->lastNameFromFullName($submission->full_name),
            'email' => $this->answer($answers, 'email') ?: $submission->email,
            'mobile' => $this->answer($answers, 'mobile'),
            'landline' => $this->answer($answers, 'phone') ?: $submission->phone,
            'birthday' => $this->answer($answers, 'birthday'),
            'street' => $this->answer($answers, 'street'),
            'zip' => $this->answer($answers, 'zip'),
            'city' => $this->answer($answers, 'city'),
            'country' => $this->answer($answers, 'country') ?: 'Deutschland',
            'entry_date' => $entryDate ?: now()->toDateString(),
            'consent_email' => (bool) ($answers['consent_email'] ?? false),
            'consent_phone' => (bool) ($answers['consent_phone'] ?? false),
            'consent_data_processing' => (bool) ($answers['consent_data_processing'] ?? false),
            'consent_given_at' => now(),
        ];
    }

    private function contactPayloadFromSubmission(PublicFormSubmission $submission, array $answers): array
    {
        $organization = $this->answer($answers, 'organization');

        return [
            'tenant_id' => $submission->tenant_id,
            'contact_type' => blank($organization) ? 'person' : 'organization',
            'organization' => $organization,
            'first_name' => $this->answer($answers, 'first_name') ?: $this->firstNameFromFullName($submission->full_name),
            'last_name' => $this->answer($answers, 'last_name') ?: $this->lastNameFromFullName($submission->full_name),
            'email' => $this->answer($answers, 'email') ?: $submission->email,
            'mobile' => $this->answer($answers, 'mobile'),
            'phone' => $this->answer($answers, 'phone') ?: $submission->phone,
            'street' => $this->answer($answers, 'street'),
            'zip' => $this->answer($answers, 'zip'),
            'city' => $this->answer($answers, 'city'),
            'country' => $this->answer($answers, 'country') ?: 'Deutschland',
            'notes' => $this->answer($answers, 'notes'),
            'source' => 'public_form:' . $submission->form?->slug,
            'consent_email' => (bool) ($answers['consent_email'] ?? false),
            'consent_phone' => (bool) ($answers['consent_phone'] ?? false),
            'consent_given_at' => now(),
        ];
    }

    private function participantPayloadFromSubmission(PublicFormSubmission $submission, array $answers, array $validated): array
    {
        if (($validated['participant_type'] ?? null) === 'member') {
            $member = Member::query()
                ->where('tenant_id', $submission->tenant_id)
                ->findOrFail($validated['member_id']);

            return [
                'member_id' => $member->id,
                'contact_id' => null,
                'first_name' => $member->first_name ?: ($member->full_name ?: 'Mitglied'),
                'last_name' => $member->last_name ?: '',
                'organization_name' => $member->organization,
                'email' => $member->email,
                'phone' => $member->mobile ?: $member->landline,
            ];
        }

        if (($validated['participant_type'] ?? null) === 'contact') {
            $contact = Contact::query()
                ->where('tenant_id', $submission->tenant_id)
                ->findOrFail($validated['contact_id']);

            return [
                'member_id' => null,
                'contact_id' => $contact->id,
                'first_name' => $contact->first_name ?: ($contact->display_name ?: 'Kontakt'),
                'last_name' => $contact->last_name ?: '',
                'organization_name' => $contact->organization ?: $contact->company,
                'email' => $contact->primary_email,
                'phone' => $contact->primary_phone,
            ];
        }

        return [
            'member_id' => null,
            'contact_id' => null,
            'first_name' => $this->answer($answers, 'first_name') ?: $this->firstNameFromFullName($submission->full_name),
            'last_name' => $this->answer($answers, 'last_name') ?: $this->lastNameFromFullName($submission->full_name),
            'organization_name' => $this->answer($answers, 'organization'),
            'email' => $this->answer($answers, 'email') ?: $submission->email,
            'phone' => $this->answer($answers, 'mobile') ?: ($this->answer($answers, 'phone') ?: $submission->phone),
        ];
    }

    private function answer(array $answers, string $key): ?string
    {
        $value = $answers[$key] ?? null;

        if (is_array($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function hasPersonOrOrganization(array $payload): bool
    {
        return filled($payload['organization'] ?? null)
            || filled($payload['organization_name'] ?? null)
            || filled($payload['first_name'] ?? null)
            || filled($payload['last_name'] ?? null)
            || filled($payload['email'] ?? null);
    }

    private function findDuplicateMember(array $payload, int $tenantId): ?array
    {
        if (filled($payload['email'] ?? null)) {
            $member = Member::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($payload['email'])])
                ->first();

            if ($member) {
                return ['record' => $member, 'reason' => 'gleiche E-Mail'];
            }
        }

        if (filled($payload['first_name'] ?? null) && filled($payload['last_name'] ?? null)) {
            $member = Member::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($payload['first_name'])])
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($payload['last_name'])])
                ->when(filled($payload['birthday'] ?? null), fn ($query) => $query->where('birthday', $payload['birthday']))
                ->first();

            if ($member) {
                return [
                    'record' => $member,
                    'reason' => filled($payload['birthday'] ?? null) ? 'gleicher Name und Geburtstag' : 'gleicher Name',
                ];
            }
        }

        if (filled($payload['organization'] ?? null)) {
            $member = Member::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(organization) = ?', [mb_strtolower($payload['organization'])])
                ->when(filled($payload['city'] ?? null), fn ($query) => $query->whereRaw('LOWER(city) = ?', [mb_strtolower($payload['city'])]))
                ->first();

            if ($member) {
                return [
                    'record' => $member,
                    'reason' => filled($payload['city'] ?? null) ? 'gleiche Organisation und gleicher Ort' : 'gleiche Organisation',
                ];
            }
        }

        return null;
    }

    private function findDuplicateContact(array $payload, int $tenantId): ?array
    {
        if (filled($payload['email'] ?? null)) {
            $contact = Contact::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($payload) {
                    $email = mb_strtolower($payload['email']);

                    $query->whereRaw('LOWER(email) = ?', [$email])
                        ->orWhereRaw('LOWER(secondary_email) = ?', [$email]);
                })
                ->first();

            if ($contact) {
                return ['record' => $contact, 'reason' => 'gleiche E-Mail'];
            }
        }

        if (filled($payload['organization'] ?? null)) {
            $contact = Contact::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($payload) {
                    $organization = mb_strtolower($payload['organization']);

                    $query->whereRaw('LOWER(organization) = ?', [$organization])
                        ->orWhereRaw('LOWER(company) = ?', [$organization]);
                })
                ->when(filled($payload['city'] ?? null), fn ($query) => $query->whereRaw('LOWER(city) = ?', [mb_strtolower($payload['city'])]))
                ->first();

            if ($contact) {
                return [
                    'record' => $contact,
                    'reason' => filled($payload['city'] ?? null) ? 'gleiche Organisation und gleicher Ort' : 'gleiche Organisation',
                ];
            }
        }

        if (filled($payload['first_name'] ?? null) && filled($payload['last_name'] ?? null)) {
            $contact = Contact::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($payload['first_name'])])
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($payload['last_name'])])
                ->when(filled($payload['city'] ?? null), fn ($query) => $query->whereRaw('LOWER(city) = ?', [mb_strtolower($payload['city'])]))
                ->first();

            if ($contact) {
                return [
                    'record' => $contact,
                    'reason' => filled($payload['city'] ?? null) ? 'gleicher Name und gleicher Ort' : 'gleicher Name',
                ];
            }
        }

        return null;
    }

    private function firstNameFromFullName(?string $fullName): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        return count($parts) > 1 ? $parts[0] : null;
    }

    private function lastNameFromFullName(?string $fullName): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        if ($parts === []) {
            return null;
        }

        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $parts[0];
    }

    private function normalizeConfirmationMail(array $validated): array
    {
        if (!($validated['confirmation_mail_enabled'] ?? false)) {
            $validated['confirmation_mail_subject'] = null;
            $validated['confirmation_mail_body'] = null;

            return $validated;
        }

        $validated['confirmation_mail_subject'] = trim((string) ($validated['confirmation_mail_subject'] ?? ''));
        $validated['confirmation_mail_body'] = trim((string) ($validated['confirmation_mail_body'] ?? ''));

        if ($validated['confirmation_mail_subject'] === '') {
            $validated['confirmation_mail_subject'] = 'Deine Nachricht an {verein}';
        }

        if ($validated['confirmation_mail_body'] === '') {
            $validated['confirmation_mail_body'] = '<p>{anrede},</p><p>vielen Dank für deine Nachricht über das Formular <strong>{formular}</strong>.</p><p>Wir melden uns so schnell wie möglich zurück.</p><p>Viele Grüße<br>{verein}</p>';
        }

        return $validated;
    }

    private function sendConfirmationMail(PublicForm $form, PublicFormSubmission $submission, array $answers): void
    {
        if (!$form->confirmation_mail_enabled) {
            return;
        }

        $recipientEmail = trim((string) ($submission->email ?: ($answers['email'] ?? '')));

        if ($recipientEmail === '') {
            return;
        }

        $tenant = $form->tenant()->first();

        if (!$tenant) {
            return;
        }

        $this->tenantMailConfigurator->apply($tenant);

        $payload = array_merge($answers, [
            'tenant_id' => $tenant->id,
            'form_title' => $form->title,
            'email' => $recipientEmail,
            'phone' => $submission->phone ?: ($answers['phone'] ?? ($answers['mobile'] ?? '')),
        ]);

        $subject = TemplateParser::parse((string) $form->confirmation_mail_subject, $payload, $tenant);
        $body = TemplateParser::parse((string) $form->confirmation_mail_body, $payload, $tenant);

        $fromAddress = $tenant->mail_from_address ?: config('mail.from.address');
        $fromName = $tenant->mail_from_name ?: ($tenant->name ?: config('mail.from.name'));
        $replyToAddress = $tenant->email && $tenant->email !== $fromAddress
            ? $tenant->email
            : null;

        try {
            Mail::send('mail.layout', [
                'body' => $body,
                'tenant' => $tenant,
            ], function ($mail) use ($recipientEmail, $subject, $fromAddress, $fromName, $replyToAddress, $tenant) {
                $mail->to($recipientEmail)
                    ->subject($subject ?: 'Bestätigung')
                    ->from($fromAddress, $fromName);

                if ($replyToAddress) {
                    $mail->replyTo($replyToAddress, $tenant->name ?? $fromName);
                }
            });

            TemplateDispatchLog::create([
                'tenant_id' => $tenant->id,
                'template_id' => null,
                'created_by' => null,
                'channel' => 'mail',
                'action' => 'form_confirmation_sent',
                'recipient_type' => 'public_form_submission',
                'member_id' => $submission->member_id,
                'contact_id' => $submission->contact_id,
                'recipient_name' => $submission->full_name,
                'recipient_reference' => $recipientEmail,
                'subject' => $subject ?: 'Bestätigung',
                'message_excerpt' => Str::limit(strip_tags($body), 240),
                'dispatched_at' => now(),
                'meta' => [
                    'public_form_id' => $form->id,
                    'public_form_title' => $form->title,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Formular-Bestätigungsmail fehlgeschlagen', [
                'form_id' => $form->id,
                'submission_id' => $submission->id,
                'email' => $recipientEmail,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolvePublicForm(string $slug): PublicForm
    {
        return PublicForm::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['fields', 'event', 'tenant'])
            ->firstOrFail();
    }

    private function ensureEventBillingFields(PublicForm $form): void
    {
        if ($form->form_type !== 'event' || !$form->event || !$form->event->is_paid) {
            return;
        }

        $billingFields = [
            ['label' => 'Strasse und Hausnummer', 'slug' => 'street', 'field_type' => 'text', 'is_required' => true, 'help_text' => 'Bei kostenpflichtigen Events fuer die Rechnungsadresse.', 'placeholder' => 'Musterstrasse 12'],
            ['label' => 'PLZ', 'slug' => 'zip', 'field_type' => 'text', 'is_required' => true, 'help_text' => 'Bei kostenpflichtigen Events fuer die Rechnungsadresse.', 'placeholder' => '12345'],
            ['label' => 'Ort', 'slug' => 'city', 'field_type' => 'text', 'is_required' => true, 'help_text' => 'Bei kostenpflichtigen Events fuer die Rechnungsadresse.', 'placeholder' => 'Musterstadt'],
            ['label' => 'Land', 'slug' => 'country', 'field_type' => 'text', 'is_required' => false, 'help_text' => 'Optional, Standard ist Deutschland.', 'placeholder' => 'Deutschland'],
        ];

        $existingFields = $form->fields()->get()->keyBy('slug');
        $sortOrder = (int) ($form->fields()->max('sort_order') ?? 0);

        foreach ($billingFields as $field) {
            $form->fields()->updateOrCreate(
                ['slug' => $field['slug']],
                $field + ['sort_order' => $existingFields[$field['slug']]->sort_order ?? ++$sortOrder]
            );
        }

        $form->load('fields');
    }

    private function authorizeForm(PublicForm $form): void
    {
        abort_if($form->tenant_id !== auth()->user()->tenant_id, 403, 'Unberechtigter Zugriff.');
    }

    private function authorizeField(PublicForm $form, PublicFormField $field): void
    {
        abort_if($field->public_form_id !== $form->id, 404);
    }

    private function authorizeSubmission(PublicForm $form, PublicFormSubmission $submission): void
    {
        abort_if($submission->public_form_id !== $form->id, 404);
    }

    private function generateBookingReference(Event $event): string
    {
        do {
            $reference = sprintf(
                'EVT-%d-%s',
                $event->id,
                Str::upper(Str::random(6))
            );
        } while (EventBooking::query()->where('booking_reference', $reference)->exists());

        return $reference;
    }

    private function seedStarterFields(PublicForm $form): void
    {
        if ($form->fields()->exists()) {
            return;
        }

        $definitions = match ($form->form_type) {
            'contact' => [
                ['label' => 'Name', 'slug' => 'name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 2],
                ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 3],
                ['label' => 'Nachricht', 'slug' => 'message', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 4],
            ],
            'membership' => [
                ['label' => 'Vorname', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                ['label' => 'Nachname', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
                ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 3],
                ['label' => 'Mobilnummer', 'slug' => 'mobile', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
                ['label' => 'Geburtstag', 'slug' => 'birthday', 'field_type' => 'date', 'is_required' => false, 'sort_order' => 5],
                ['label' => 'Straße + Nr.', 'slug' => 'street', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 6],
                ['label' => 'PLZ', 'slug' => 'zip', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 7],
                ['label' => 'Ort', 'slug' => 'city', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 8],
            ],
            'event' => [
                ['label' => 'Vorname', 'slug' => 'first_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                ['label' => 'Nachname', 'slug' => 'last_name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
                ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 3],
                ['label' => 'Telefon', 'slug' => 'phone', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 4],
            ],
            default => [
                ['label' => 'Name', 'slug' => 'name', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                ['label' => 'E-Mail', 'slug' => 'email', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 2],
                ['label' => 'Nachricht', 'slug' => 'message', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 3],
            ],
        };

        foreach ($definitions as $definition) {
            $form->fields()->create($definition);
        }
    }

    private function normalizeSortOrder(PublicForm $form): void
    {
        $form->fields()
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->each(function (PublicFormField $field, int $index) {
                $field->update(['sort_order' => $index + 1]);
            });
    }
}
