@if (!empty($post->simpleviewEventData))
    <ul class="c-simpleview-event-single__meta unlist">
        {{-- Calendar / Media channel --}}
        @if (!empty($post->simpleviewEventData->mediaChannelName))
            <li class="c-simpleview-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-calendar'])
                @endicon
                <span>{{ $post->simpleviewEventData->mediaChannelName }}</span>
            </li>
        @endif

        {{-- Date + time range (end time only if provided) --}}
        @if (!empty($post->simpleviewEventData->dateTimeLabel))
            <li class="c-simpleview-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-calendar-days'])
                @endicon
                <span>{{ $post->simpleviewEventData->dateTimeLabel }}</span>
            </li>
        @elseif (!empty($post->simpleviewEventData->startDate))
            <li class="c-simpleview-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-calendar-days'])
                @endicon
                <span>{{ $post->simpleviewEventData->startDate }}</span>
            </li>
        @endif

        {{-- Location / Address (full address when available, else location name) --}}
        @if (!empty($post->simpleviewEventData->addressFormatted) || !empty($post->simpleviewEventData->locationName))
            <li class="c-simpleview-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-location-dot'])
                @endicon
                <span>{{ $post->simpleviewEventData->addressFormatted ?? $post->simpleviewEventData->locationName }}</span>
                @if (!empty($post->simpleviewEventData->googleMapsUrl))
                    <a href="{{ $post->simpleviewEventData->googleMapsUrl }}" target="_blank" rel="noopener noreferrer" class="u-margin__left--1">Visa på Google Maps</a>
                @endif
            </li>
        @endif
    </ul>

    {{-- Contact info --}}
    @if (!empty($post->simpleviewEventData->contact) && (!empty($post->simpleviewEventData->contact->telephone) || !empty($post->simpleviewEventData->contact->email)))
        <div class="c-simpleview-event-single__contact u-margin__top--4">
            <h3 class="u-margin__bottom--2">Kontaktuppgifter</h3>
            <ul class="unlist">
                @if (!empty($post->simpleviewEventData->contact->telephone))
                    <li>
                        <strong>Telefon</strong>
                        <a href="tel:{{ preg_replace('/\s+/', '', $post->simpleviewEventData->contact->telephone) }}">{{ $post->simpleviewEventData->contact->telephone }}</a>
                    </li>
                @endif
                @if (!empty($post->simpleviewEventData->contact->email))
                    <li>
                        <strong>E-post</strong>
                        <a href="mailto:{{ $post->simpleviewEventData->contact->email }}">{{ $post->simpleviewEventData->contact->email }}</a>
                    </li>
                @endif
            </ul>
        </div>
    @endif
@endif
