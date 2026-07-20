<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use PDF; // Richtige Fassade für niklasravnsborg/laravel-pdf

class PdfTestController extends Controller
{
    public function test()
    {
        $tenant = Auth::user()->tenant;

        $data = [
            'name' => $tenant->name,
            'city' => $tenant->city,
            'date' => now()->format('d.m.Y'),
            'pdf_template' => $tenant->use_letterhead ? $tenant->pdf_template : null,
        ];

        return PDF::loadView('members.pdf.testbrief', $data)->stream('briefbogen-test.pdf');
    }
}
