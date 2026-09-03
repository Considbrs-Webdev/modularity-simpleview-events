<div class="mod-sv-events">
    @if (!$hideTitle && $postTitle || !empty($calendarUrl))
        <div class="mod-sv-events__header">
            @if (!$hideTitle && $postTitle)
                @typography([
                    'element' => 'h2',
                    'variant' => 'h2',
                    'classList' => ['mod-sv-events__title'],
                ])
                    {{ $postTitle }}
                @endtypography
            @endif

            @if (!empty($calendarUrl))
                <a href="{{ esc_url($calendarUrl) }}" class="mod-sv-events__link">
                    {{ __('Till evenemangskalendern', 'modularity-simpleview-events') }}
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 4L11.293 4.707L13.586 7H2V8H13.586L11.293 10.293L12 11L15.5 7.5L12 4Z" fill="currentColor"/>
                    </svg>
                </a>
            @endif
        </div>
    @endif

    @if (empty($events))
        @typography([
            'element' => 'p',
            'classList' => ['mod-sv-events__empty'],
        ])
            {{ $emptyMessage ?? '' }}
        @endtypography
    @else
        <ul class="mod-sv-events__container">
            @foreach ($events as $event)
                <li class="c-event-card">
                    <div class="c-event-card__image-wrapper">
                        @if (!empty($event['image']))
                            <img
                                src="{{ esc_url($event['image']) }}"
                                alt="{{ esc_attr($event['title'] ?? '') }}"
                                class="c-event-card__image"
                                loading="lazy"
                            />
                        @endif
                        @if (!empty($event['badgeDate']))
                            <div class="c-event-card__badge">{{ $event['badgeDate'] }}</div>
                        @endif
                    </div>
                    <div class="c-event-card__content">
                        <h3 class="c-event-card__title">
                            <a href="{{ esc_url($event['link'] ?? '#') }}" class="c-event-card__link">
                                {{ $event['title'] ?? '' }}
                            </a>
                        </h3>
                        <div class="c-event-card__meta">
                            @if (!empty($event['dateSpan']))
                                <div class="c-event-card__meta-item">
                                    <i class="fas fa-calendar c-event-card__icon" style="color: {{ esc_attr($iconColor ?? '#666666') }};" aria-hidden="true"></i>
                                    <span>{{ $event['dateSpan'] }}</span>
                                </div>
                            @endif
                            @if (!empty($event['location']))
                                <div class="c-event-card__meta-item">
                                    <i class="fas fa-map-marker-alt c-event-card__icon" style="color: {{ esc_attr($iconColor ?? '#666666') }};" aria-hidden="true"></i>
                                    <span>{{ $event['location'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
