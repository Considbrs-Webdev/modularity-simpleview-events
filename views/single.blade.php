@extends('templates.single')

@section('loop')
    @if (!empty($post))
        @if (!empty($post->simpleviewEventData))
            {{-- Card used only for image + date badge (no link, no heading, no content) --}}
            @card([
                'link' => false,
                'image' => $post->simpleviewEventData->imageHero ?? $post->simpleviewEventData->image ?? null,
                'date' => $post->simpleviewEventData->date ?? null,
                'dateBadge' => !empty($post->simpleviewEventData->dateBadge),
                'heading' => '',
                'classList' => ['c-simpleview-event-single__hero', 'c-simpleview-event-card'],
                'context' => ['single', 'single.hero'],
                'containerAware' => true,
                'attributeList' => [
                    'aria-label' => $post->simpleviewEventData->ariaLabel ?? ''
                ]
            ])
            @endcard
        @endif
        @element([
            'componentElement' => 'article',
            'id' => 'article',
            'classList' => ['c-article', 'c-article--readable-width', 's-article', 'u-clearfix']
        ])
            {{-- Title --}}
            @section('article.title.before')@show
            @section('article.title')
                @if (method_exists($post, 'getTitle') ? $post->getTitle() : $post->post_title)
                    @typography([
                        'element' => 'h1',
                        'variant' => 'h1',
                        'id' => 'page-title',
                        'classList' => ['u-margin__bottom--4']
                    ])
                        {!! method_exists($post, 'getTitle') ? $post->getTitle() : $post->post_title !!}
                    @endtypography
                @endif
            @show
            @section('article.title.after')@show

            {{-- Simpleview Event Metadata (same as archive card) --}}
            @if (!empty($post->simpleviewEventData))
                <div class="c-simpleview-event-single__meta-wrapper u-margin__bottom--5">
                    @include('partials.simpleview-event.meta')
                </div>
            @endif

            {{-- Content --}}
            @section('article.content.before')@show
            {!! $hook->articleContentBefore ?? '' !!}
            @section('article.content')
                {!! is_object($post) && method_exists($post, 'getContent') ? $post->getContent() : $post->post_content !!}
            @show
            @section('article.content.after')@show

            {!! $hook->articleContentAfter ?? '' !!}

            {{-- Signature --}}
            @section('content.below')
                @includeWhen(
                    !empty($signature),
                    'partials.signature',
                    array_merge((array) ($signature ?? []), ['classList' => []]))
            @endsection
        @endelement
    @endif
@stop
