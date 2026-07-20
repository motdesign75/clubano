<?php

namespace App\Http\Requests;

class UpdateContactRequest extends StoreContactRequest
{
    public function rules(): array
    {
        return parent::rules();
    }
}
