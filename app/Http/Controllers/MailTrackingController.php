<?php

namespace App\Http\Controllers;

use App\Models\TemplateDispatchLog;
use App\Models\OperatorAnnouncementDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MailTrackingController extends Controller
{
    public function open(string $token): Response
    {
        $log = TemplateDispatchLog::query()
            ->where('channel', 'mail')
            ->where('tracking_token', $token)
            ->first();

        if ($log) {
            $log->registerOpen();
        }

        $pixel = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function click(Request $request, TemplateDispatchLog $dispatchLog)
    {
        abort_unless($request->hasValidSignature(), 403);

        $target = (string) $request->query('target', '');

        if (! filter_var($target, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($target, PHP_URL_SCHEME), ['http', 'https'], true)) {
            abort(404);
        }

        if ($dispatchLog->channel === 'mail') {
            $dispatchLog->registerClick($target);
        }

        return redirect()->away($target);
    }

    public function operatorOpen(string $token): Response
    {
        $delivery = OperatorAnnouncementDelivery::query()
            ->where('tracking_token', $token)
            ->first();

        if ($delivery) {
            $delivery->registerOpen();
        }

        $pixel = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function operatorClick(Request $request, OperatorAnnouncementDelivery $delivery)
    {
        abort_unless($request->hasValidSignature(), 403);

        $target = (string) $request->query('target', '');

        if (! filter_var($target, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($target, PHP_URL_SCHEME), ['http', 'https'], true)) {
            abort(404);
        }

        $delivery->registerClick($target);

        return redirect()->away($target);
    }
}
