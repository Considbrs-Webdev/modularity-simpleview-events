@card([
    'link' => method_exists($post, 'getPermalink') ? $post->getPermalink() : get_permalink($post->ID ?? 0),
    'heading' => method_exists($post, 'getTitle') ? $post->getTitle() : ($post->post_title ?? ''),
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
        </ul>
    @endslot
@endcard
