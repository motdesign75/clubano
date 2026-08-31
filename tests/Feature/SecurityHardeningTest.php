<?php

use App\Models\PublicForm;
use App\Models\PublicFormField;
use App\Models\Tenant;
use Illuminate\Support\Str;

function createPublicSecurityForm(): PublicForm
{
    $tenant = Tenant::create([
        'name' => 'Sicherheitsverein',
        'slug' => 'sicherheitsverein-' . Str::random(6),
        'email' => 'sicherheit@example.test',
    ]);

    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'title' => 'Kontakt',
        'slug' => 'kontakt-' . Str::random(8),
        'form_type' => 'contact',
        'success_message' => 'Danke.',
        'is_active' => true,
    ]);

    PublicFormField::create([
        'public_form_id' => $form->id,
        'label' => 'E-Mail',
        'slug' => 'email',
        'field_type' => 'email',
        'is_required' => true,
        'sort_order' => 1,
    ]);

    return $form;
}

test('web responses include defensive browser security headers', function () {
    $response = $this->get('/impressum');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
});

test('public embeds can still be embedded by external club websites', function () {
    $form = createPublicSecurityForm();

    $response = $this->get(route('forms.public.embed', $form->slug));

    $response->assertOk();
    $response->assertHeaderMissing('X-Frame-Options');
    $response->assertHeader('Content-Security-Policy', 'frame-ancestors *');
});

test('public form submissions are rate limited', function () {
    $form = createPublicSecurityForm();

    for ($i = 0; $i < 10; $i++) {
        $this->post(route('forms.public.submit', $form->slug), [
            'fields' => [
                'email' => 'mitglied'.$i.'@example.test',
            ],
        ])->assertRedirect();
    }

    $this->post(route('forms.public.submit', $form->slug), [
        'fields' => [
            'email' => 'zu-viel@example.test',
        ],
    ])->assertTooManyRequests();
});
