<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateNoticeController extends Controller
{
    public function dismiss(Request $request): RedirectResponse
    {
        $version = (string) config('clubano.update_notice.version');

        if ($version !== '') {
            $request->user()?->forceFill([
                'update_notice_dismissed_version' => $version,
            ])->save();
        }

        return back();
    }
}
