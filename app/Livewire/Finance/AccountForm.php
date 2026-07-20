<?php

namespace App\Livewire\Finance;

use App\Models\Account;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class AccountForm extends Component
{
    public $name = '';
    public $type = 'kasse';
    public $iban = '';
    public $bic = '';
    public $description = '';
    public $active = true;

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bank,kasse',
            'iban' => 'nullable|string|max:255',
            'bic' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        Account::create([
            'tenant_id' => tenant()->id,
            'name' => $this->name,
            'type' => $this->type,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'description' => $this->description,
            'active' => $this->active,
        ]);

        session()->flash('success', 'Konto erfolgreich angelegt.');
        return redirect()->route('finance.accounts');
    }

    public function render()
    {
        return view('livewire.finance.account-form');
    }
}
