@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-simpleview-event' => true
    ]
])
    @card([
        'heading' => '{SEARCH_HIT_HEADING}',
        'image' => [
            'src' => '{SEARCH_HIT_IMAGE_URL}',
            'alt' => '{SEARCH_HIT_IMAGE_ALT}'
        ],
        'link' => '{SEARCH_HIT_LINK}',
        'classList' => ['c-card--size-md', 'u-height--100', 'c-simpleview-event-card'],
        'context' => ['archive', 'archive.list', 'archive.list.card'],
        'containerAware' => true,
        'attributeList' => ['aria-label' => '{SEARCH_HIT_ARIA_LABEL}']
    ])
        @slot('content')
            <ul class="c-simpleview-event-card__meta unlist">
                <li class="c-simpleview-event-card__meta-item">
                    @icon(['icon' => 'fa-solid fa-calendar'])
                    @endicon
                    <span>{SEARCH_HIT_MEDIA_CHANNEL}</span>
                </li>
                <li class="c-simpleview-event-card__meta-item">
                    @icon(['icon' => 'fa-solid fa-calendar-days'])
                    @endicon
                    <span>{SEARCH_HIT_DATE_TIME_LABEL}</span>
                </li>
                <li class="c-simpleview-event-card__meta-item">
                    @icon(['icon' => 'fa-solid fa-location-dot'])
                    @endicon
                    <span>{SEARCH_HIT_LOCATION}</span>
                </li>
            </ul>
        @endslot
    @endcard
@endelement
