@php
    $googleAnalyticsMeasurementId = config('services.google_analytics.measurement_id');
@endphp

@if(filled($googleAnalyticsMeasurementId))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($googleAnalyticsMeasurementId) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        gtag('config', @js($googleAnalyticsMeasurementId));
    </script>
@endif
