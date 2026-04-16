@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-simpleview-event' => true
    ]
])
    <div class="c-card c-card--size-md c-card--action c-simpleview-event-card ts-search-hit-card">
        <div class="c-card__paint-container">
            <div class="c-card__body">
                <div class="c-group c-group--vertical c-group--gap-1">
                    <div class="c-group c-group--horizontal c-group--align-items-center c-group--gap-1">
                        <span class="c-badge c-badge--primary">{SEARCH_HIT_MEDIA_CHANNEL}</span>
                        <span class="c-typography">{SEARCH_HIT_DATE_TIME_LABEL}</span>
                    </div>
                    <h2 class="c-typography c-card__heading u-margin__y--0 c-typography__variant--h3">
                        <a class="ts-search-hit-card__link" href="{SEARCH_HIT_LINK}">{SEARCH_HIT_HEADING}</a>
                    </h2>
                    <span class="c-typography c-typography__variant--meta u-margin__y--0">
                        <i class="fa-solid fa-location-pin" aria-hidden="true"></i>
                        {SEARCH_HIT_LOCATION}
                    </span>
                    <p class="c-typography c-card__content c-typography__variant--p u-margin__y--0">
                        {SEARCH_HIT_EXCERPT}
                    </p>
                </div>
            </div>
        </div>
    </div>
@endelement
