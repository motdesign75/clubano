<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'     => $this->boolean('is_active'),
            'is_favorite'   => $this->boolean('is_favorite'),
            'consent_email' => $this->boolean('consent_email'),
            'consent_phone' => $this->boolean('consent_phone'),
            'consent_post'  => $this->boolean('consent_post'),
            'gdpr_consent'  => $this->boolean('consent_email')
                || $this->boolean('consent_phone')
                || $this->boolean('consent_post')
                || $this->boolean('gdpr_consent'),
        ]);
    }

    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Status / Art
            |--------------------------------------------------------------------------
            */

            'contact_type' => [
                'required',
                'string',
                'in:person,organization',
            ],

            'category' => [
                'nullable',
                'string',
                'max:100',
            ],

            'is_active' => [
                'boolean',
            ],

            'is_favorite' => [
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Organisation
            |--------------------------------------------------------------------------
            */

            'organization' => [
                'nullable',
                'string',
                'max:150',
                'required_without_all:first_name,last_name',
            ],

            'department' => [
                'nullable',
                'string',
                'max:150',
            ],

            'position' => [
                'nullable',
                'string',
                'max:150',
            ],

            /*
            |--------------------------------------------------------------------------
            | Person
            |--------------------------------------------------------------------------
            */

            'gender' => [
                'nullable',
                'string',
                'max:30',
            ],

            'salutation' => [
                'nullable',
                'string',
                'max:50',
            ],

            'title' => [
                'nullable',
                'string',
                'max:50',
            ],

            'first_name' => [
                'nullable',
                'string',
                'max:100',
                'required_without:organization',
            ],

            'last_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'birthday' => [
                'nullable',
                'date',
            ],

            'photo' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Kommunikation
            |--------------------------------------------------------------------------
            */

            'email' => [
                'nullable',
                'email',
                'max:190',
            ],

            'secondary_email' => [
                'nullable',
                'email',
                'max:190',
            ],

            'mobile' => [
                'nullable',
                'string',
                'max:50',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'fax' => [
                'nullable',
                'string',
                'max:50',
            ],

            'website' => [
                'nullable',
                'url',
                'max:200',
            ],

            /*
            |--------------------------------------------------------------------------
            | Adresse
            |--------------------------------------------------------------------------
            */

            'street' => [
                'nullable',
                'string',
                'max:150',
            ],

            'address_addition' => [
                'nullable',
                'string',
                'max:150',
            ],

            'zip' => [
                'nullable',
                'string',
                'max:20',
            ],

            'city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],

            'care_of' => [
                'nullable',
                'string',
                'max:150',
            ],

            /*
            |--------------------------------------------------------------------------
            | Beziehung / Herkunft
            |--------------------------------------------------------------------------
            */

            'relationship' => [
                'nullable',
                'string',
                'max:150',
            ],

            'source' => [
                'nullable',
                'string',
                'max:100',
            ],

            'responsible_user_id' => [
                'nullable',
                'exists:users,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Datenschutz / Einwilligung
            |--------------------------------------------------------------------------
            */

            'consent_email' => [
                'boolean',
            ],

            'consent_phone' => [
                'boolean',
            ],

            'consent_post' => [
                'boolean',
            ],

            'consent_given_at' => [
                'nullable',
                'date',
            ],

            /*
            |--------------------------------------------------------------------------
            | Verlauf / Notizen
            |--------------------------------------------------------------------------
            */

            'last_contacted_at' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'internal_notes' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Alte Felder - Kompatibilität
            |--------------------------------------------------------------------------
            */

            'company' => [
                'nullable',
                'string',
                'max:150',
            ],

            'phone_mobile' => [
                'nullable',
                'string',
                'max:50',
            ],

            'phone_landline' => [
                'nullable',
                'string',
                'max:50',
            ],

            'street_addition' => [
                'nullable',
                'string',
                'max:150',
            ],

            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'status' => [
                'nullable',
                'string',
                'max:40',
            ],

            'gdpr_consent' => [
                'boolean',
            ],

            'gdpr_consent_at' => [
                'nullable',
                'date',
            ],

            'tags' => [
                'nullable',
                'array',
            ],

            'tags.*' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_type.required' => 'Bitte wähle aus, ob es sich um eine Person oder Organisation handelt.',
            'contact_type.in' => 'Der Kontakttyp ist ungültig.',
            'organization.required_without_all' => 'Bitte gib entweder eine Organisation oder einen Namen ein.',
            'first_name.required_without' => 'Bitte gib entweder einen Vornamen oder eine Organisation ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'secondary_email.email' => 'Bitte gib eine gültige zweite E-Mail-Adresse ein.',
            'website.url' => 'Bitte gib eine gültige Website-URL ein.',
            'responsible_user_id.exists' => 'Die ausgewählte verantwortliche Person ist ungültig.',
        ];
    }
}
