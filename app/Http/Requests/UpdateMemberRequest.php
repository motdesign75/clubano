<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'gender'            => 'nullable|in:weiblich,männlich,divers',
            'salutation'        => 'nullable|in:Frau,Herr,Liebe,Lieber,Hallo',
            'title'             => 'nullable|string|max:255',
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'organization'      => 'nullable|string|max:255',
            'birthday'          => 'nullable|date',
            'member_id'         => 'nullable|string|max:255',
            'entry_date'        => 'nullable|date',
            'exit_date'         => 'nullable|date',
            'termination_date'  => 'nullable|date',
            'next_membership_invoice_on' => 'nullable|date',
            'required_service_hours' => 'nullable|numeric|min:0|max:999.99',
            'payment_method'    => 'nullable|in:ueberweisung,bar,sepa_lastschrift',
            'iban'              => 'required_if:payment_method,sepa_lastschrift|nullable|string|max:34',
            'bic'               => 'nullable|string|max:11',
            'sepa_mandate_reference' => 'required_if:payment_method,sepa_lastschrift|nullable|string|max:255',
            'sepa_signed_at'    => 'required_if:payment_method,sepa_lastschrift|nullable|date',
            'sepa_account_holder' => 'nullable|string|max:255',
            'sepa_account_holder_street' => 'nullable|string|max:255',
            'sepa_account_holder_zip' => 'nullable|string|max:20',
            'sepa_account_holder_city' => 'nullable|string|max:255',
            'sepa_account_holder_country' => 'nullable|string|max:255',
            'email'             => 'nullable|email|max:255',
            'mobile'            => 'nullable|string|max:255',
            'whatsapp_phone'    => 'nullable|string|max:255',
            'landline'          => 'nullable|string|max:255',
            'preferred_contact_channel' => 'nullable|in:email,phone,whatsapp,post',
            'consent_email'     => 'nullable|boolean',
            'consent_phone'     => 'nullable|boolean',
            'consent_post'      => 'nullable|boolean',
            'consent_whatsapp'  => 'nullable|boolean',
            'consent_data_processing' => 'nullable|boolean',
            'consent_photo_internal' => 'nullable|boolean',
            'consent_photo_public' => 'nullable|boolean',
            'consent_given_at'  => 'nullable|date',
            'deletion_requested_at' => 'nullable|date',
            'deletion_note'     => 'nullable|string|max:2000',
            'street'            => 'nullable|string|max:255',
            'address_addition'  => 'nullable|string|max:255',
            'zip'               => 'nullable|string|max:20',
            'city'              => 'nullable|string|max:255',
            'country'           => 'nullable|string|max:255',
            'care_of'           => 'nullable|string|max:255',
            'membership_id'     => ['nullable', Rule::exists('memberships', 'id')->where('tenant_id', $tenantId)],
            'photo'             => 'nullable|image|max:2048',
        ];
    }
}
