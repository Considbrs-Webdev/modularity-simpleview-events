@if (!empty($post->simpleviewEventData))
    @card([
        'link' => method_exists($post, 'getPermalink') ? $post->getPermalink() : get_permalink($post->ID ?? 0),
        'image' => $post->simpleviewEventData->image ?? null,
        'date' => $post->simpleviewEventData->date ?? null,
        'dateBadge' => !empty($post->simpleviewEventData->dateBadge),
        'heading' => method_exists($post, 'getTitle') ? $post->getTitle() : $post->post_title ?? '',
        'classList' => ['u-height--100', 'c-simpleview-event-card'],
        'context' => ['archive', 'archive.list', 'archive.list.card'],
        'containerAware' => true,
        'attributeList' => [
            'aria-label' => $post->simpleviewEventData->ariaLabel ?? ''
        ]
    ])
        @slot('content')
            <ul class="c-simpleview-event-card__meta unlist">
                {{-- Media channel --}}
                @if (!empty($post->simpleviewEventData->mediaChannelName))
                    <li class="c-simpleview-event-card__meta-item">
                        @icon(['icon' => 'fa-solid fa-calendar'])
                        @endicon
                        <span>{{ $post->simpleviewEventData->mediaChannelName }}</span>
                    </li>
                @endif

                {{-- Date + time range (end time only if provided) --}}
                @if (!empty($post->simpleviewEventData->dateTimeLabel))
                    <li class="c-simpleview-event-card__meta-item">
                        @icon(['icon' => 'fa-solid fa-calendar-days'])
                        @endicon
                        <span>{{ $post->simpleviewEventData->dateTimeLabel }}</span>
                    </li>
                @elseif (!empty($post->simpleviewEventData->startDate))
                    <li class="c-simpleview-event-card__meta-item">
                        @icon(['icon' => 'fa-solid fa-calendar-days'])
                        @endicon
                        <span>{{ $post->simpleviewEventData->startDate }}</span>
                    </li>
                @endif

                {{-- Location --}}
                @if (!empty($post->simpleviewEventData->locationName))
                    <li class="c-simpleview-event-card__meta-item">
                        @icon(['icon' => 'fa-solid fa-location-dot'])
                        @endicon
                        <span>{{ $post->simpleviewEventData->locationName }}</span>
                    </li>
                @endif
            </ul>
        @endslot
    @endcard
@else
    {{-- Fallback to Municipio default PostsList card for non-Simpleview posts --}}
    @card([
        'link' => $post->getPermalink(),
        'image' => $post->getImage(),
        'heading' => $post->getTitle(),
        'content' => $getExcerpt($post, 20),
        'tags' => $getTags($post),
        'meta' => $getReadingTime($post),
        'date' => $getDateFormat()
            ? [
                'timestamp' => $getDateTimestamp($post),
                'format' => $getDateFormat()
            ]
            : null,
        'dateBadge' => $showDateBadge(),
        'classList' => ['u-height--100'],
        'context' => ['archive', 'archive.list', 'archive.list.card'],
        'containerAware' => true,
        'hasPlaceholder' => $shouldDisplayPlaceholderImage($post)
    ])
    @endcard
@endif
