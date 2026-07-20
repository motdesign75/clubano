<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use AuthorizesRequests;

    /*
    |--------------------------------------------------------------------------
    | Kontakte Übersicht
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $this->authorize('viewAny', Contact::class);

        $q = trim((string) $request->query('q', ''));
        $category = $request->query('category');
        $contactType = $request->query('contact_type');
        $status = $request->query('status');
        $favorites = $request->boolean('favorites');

        $contacts = Contact::query()
            ->search($q)
            ->category($category)
            ->when($contactType, function ($query) use ($contactType) {
                $query->where('contact_type', $contactType);
            })
            ->when($status === 'active', function ($query) {
                $query->active();
            })
            ->when($status === 'inactive', function ($query) {
                $query->inactive();
            })
            ->when($favorites, function ($query) {
                $query->favorites();
            })
            ->orderByRaw("
                COALESCE(
                    NULLIF(last_name, ''),
                    NULLIF(organization, ''),
                    NULLIF(company, ''),
                    NULLIF(first_name, ''),
                    email
                ) asc
            ")
            ->paginate(15)
            ->withQueryString();

        return view('contacts.index', [
            'contacts'    => $contacts,
            'q'           => $q,
            'category'    => $category,
            'contactType' => $contactType,
            'status'      => $status,
            'favorites'   => $favorites,
            'categories'  => $this->contactCategories(),
            'types'       => $this->contactTypes(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Kontakt erstellen
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $this->authorize('create', Contact::class);

        return view('contacts.create', [
            'contact'    => new Contact([
                'contact_type' => 'person',
                'country'      => 'Deutschland',
                'is_active'    => true,
            ]),
            'categories' => $this->contactCategories(),
            'types'      => $this->contactTypes(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Kontakt speichern
    |--------------------------------------------------------------------------
    */

    public function store(StoreContactRequest $request)
    {
        $this->authorize('create', Contact::class);

        $contact = Contact::create($request->validated());

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Kontakt angelegt.');
    }

    /*
    |--------------------------------------------------------------------------
    | Kontakt anzeigen
    |--------------------------------------------------------------------------
    */

    public function show(Contact $contact)
    {
        $this->authorize('view', $contact);

        return view('contacts.show', compact('contact'));
    }

    /*
    |--------------------------------------------------------------------------
    | Kontakt bearbeiten
    |--------------------------------------------------------------------------
    */

    public function edit(Contact $contact)
    {
        $this->authorize('update', $contact);

        return view('contacts.edit', [
            'contact'    => $contact,
            'categories' => $this->contactCategories(),
            'types'      => $this->contactTypes(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Kontakt aktualisieren
    |--------------------------------------------------------------------------
    */

    public function update(UpdateContactRequest $request, Contact $contact)
    {
        $this->authorize('update', $contact);

        $contact->update($request->validated());

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Kontakt aktualisiert.');
    }

    /*
    |--------------------------------------------------------------------------
    | Kontakt löschen
    |--------------------------------------------------------------------------
    */

    public function destroy(Contact $contact)
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()
            ->route('contacts.index')
            ->with('success', 'Kontakt gelöscht.');
    }

    /*
    |--------------------------------------------------------------------------
    | Optionen
    |--------------------------------------------------------------------------
    */

    private function contactTypes(): array
    {
        return [
            'person'       => 'Person',
            'organization' => 'Organisation',
        ];
    }

    private function contactCategories(): array
    {
        return [
            'sponsor'    => 'Sponsor',
            'supplier'   => 'Lieferant',
            'partner'    => 'Partner',
            'press'      => 'Presse',
            'authority'  => 'Behörde',
            'trainer'    => 'Trainer',
            'parent'     => 'Elternteil',
            'volunteer'  => 'Ehrenamt',
            'donor'      => 'Spender',
            'service'    => 'Dienstleister',
            'other'      => 'Sonstiges',
        ];
    }
}
