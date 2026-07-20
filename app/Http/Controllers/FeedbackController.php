<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Mail\FeedbackSubmitted;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'category' => 'required|string|in:Fehler,Verbesserung,Allgemein',
            'view' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:2048',
            'page_title' => 'nullable|string|max:255',
            'device_label' => 'nullable|string|max:120',
            'viewport' => 'nullable|string|max:50',
            'user_agent' => 'nullable|string|max:2000',
            'screenshot_data' => 'nullable|string',
            'screenshot_file' => 'nullable|image|max:4096',
        ]);

        $screenshotPath = $this->storeUploadedScreenshot($request)
            ?? $this->storeScreenshotFromDataUrl($request->input('screenshot_data'));

        $feedback = Feedback::create([
            'user_id' => auth()->id(),
            'category' => $request->input('category'),
            'view' => $request->input('view'),
            'url' => $request->input('url'),
            'page_title' => $request->input('page_title'),
            'device_label' => $request->input('device_label'),
            'viewport' => $request->input('viewport'),
            'user_agent' => $request->input('user_agent'),
            'message' => $request->input('message'),
            'screenshot_path' => $screenshotPath,
        ]);

        // Mail versenden
        Mail::to('system@clubano.de')->send(
            new FeedbackSubmitted($feedback)
        );

        return back()->with('success', 'Vielen Dank für dein Feedback!');
    }

    private function storeScreenshotFromDataUrl(?string $dataUrl): ?string
    {
        if (blank($dataUrl) || !preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl, $matches)) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
        $payload = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($payload, true);

        if ($binary === false) {
            return null;
        }

        $path = 'feedback/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function storeUploadedScreenshot(Request $request): ?string
    {
        if (!$request->hasFile('screenshot_file')) {
            return null;
        }

        return $request->file('screenshot_file')->store(
            'feedback/' . now()->format('Y/m'),
            'public'
        );
    }
}
