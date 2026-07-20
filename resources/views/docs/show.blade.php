@extends('docs.layout', ['title' => $pageTitle, 'group' => $pageGroup, 'navigation' => $navigation])

@section('content')
    <article class="docs-article">
        {!! $content !!}
    </article>

    @if (!empty($screenshots))
        <section class="docs-screenshots">
            <div class="docs-screenshots-head">
                <h3>Screenshot-Bereich</h3>
                <p>
                    Die Dokumentation ist schon für Bilder vorbereitet. Solange die finalen Screenshots noch nicht
                    eingesetzt sind, bleiben diese Platzhalter als saubere Orientierung sichtbar.
                </p>
            </div>

            <div class="docs-shot-grid">
                @foreach ($screenshots as $screenshot)
                    <article class="docs-shot">
                        @if ($screenshot['exists'] && $screenshot['url'])
                            <div class="docs-shot-preview">
                                <img src="{{ $screenshot['url'] }}" alt="{{ $screenshot['filename'] }}">
                            </div>
                        @else
                            <div class="docs-shot-preview placeholder">
                                <div>
                                    <span>Screenshot folgt später</span>
                                    <p>{{ $screenshot['filename'] }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="docs-shot-meta">
                            <strong>{{ $screenshot['filename'] }}</strong>
                            <p>
                                {{ $screenshot['exists'] ? 'Dieses Bild ist bereits hinterlegt.' : 'Hier ist bewusst ein Platzhalter vorgesehen, bis der finale Screenshot eingefügt wird.' }}
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
