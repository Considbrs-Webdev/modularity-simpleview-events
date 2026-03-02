@element([
    'componentElement' => 'template',
    'attributeList' => [
        'data-js-search-hit-template-simpleview-event' => true
    ]
])
    <a class="c-card c-card--size-md c-card--action c-simpleview-event-card" aria-label="{SEARCH_HIT_ARIA_LABEL}"
        href="{SEARCH_HIT_LINK}">
        <div class="c-card__paint-container">
            <div class="c-card__body">
                <div class="c-group c-group--vertical c-group--gap-1">
                    <div class="c-group c-group--horizontal c-group--align-items-center c-group--gap-1">
                        <span class="c-typography c-card__sub-heading u-margin__y--0 c-typography__variant--h6">
                            {SEARCH_HIT_MEDIA_CHANNEL}
                        </span>
                        <span class="u-color__text--primary u-display--inline-flex u-align-items--center" aria-hidden="true">
                            <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor" aria-hidden="true">
                                <circle cx="4" cy="4" r="4" />
                            </svg>
                        </span>
                        <span class="c-typography">{SEARCH_HIT_DATE_TIME_LABEL}</span>
                    </div>
                    <h2 class="c-typography c-card__heading u-margin__y--0 c-typography__variant--h3">
                        {SEARCH_HIT_HEADING}
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
    </a>
@endelement
