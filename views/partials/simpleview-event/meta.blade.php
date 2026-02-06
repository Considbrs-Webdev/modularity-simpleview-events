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

        {{-- Location --}}
        @if (!empty($post->simpleviewEventData->locationName))
            <li class="c-simpleview-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-location-dot'])
                @endicon
                <span>{{ $post->simpleviewEventData->locationName }}</span>
            </li>
        @endif
    </ul>
@endif
