@if (!empty($post->municipalEventData))
    <ul class="c-municipal-event-single__meta unlist">
        {{-- Administration --}}
        @if (!empty($post->municipalEventData->administration))
            <li class="c-municipal-event-single__meta-item">
                @if ($post->municipalEventData->administrationIcon)
                    @icon(['icon' => $post->municipalEventData->administrationIcon])
                    @endicon
                @else
                    @icon(['icon' => 'fa-solid fa-building'])
                    @endicon
                @endif
                <span>{{ $post->municipalEventData->administration->name }}</span>
            </li>
        @endif

        {{-- Time Range --}}
        @if (!empty($post->municipalEventData->timeRange))
            <li class="c-municipal-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-clock'])
                @endicon
                <span>{{ $post->municipalEventData->timeRange }}</span>
            </li>
        @endif

        {{-- Place --}}
        @if (!empty($post->municipalEventData->place))
            <li class="c-municipal-event-single__meta-item">
                @if ($post->municipalEventData->placeIcon)
                    @icon(['icon' => $post->municipalEventData->placeIcon])
                    @endicon
                @else
                    @icon(['icon' => 'fa-solid fa-map-marker-alt'])
                    @endicon
                @endif
                <span>{{ $post->municipalEventData->place->name }}</span>
            </li>
        @endif

        {{-- Type of event/meeting --}}
        @if (!empty($post->municipalEventData->type))
            <li class="c-municipal-event-single__meta-item">
                @if ($post->municipalEventData->typeIcon)
                    @icon(['icon' => $post->municipalEventData->typeIcon])
                    @endicon
                @else
                    @icon(['icon' => 'fa-solid fa-calendar-check'])
                    @endicon
                @endif
                <span>{{ $post->municipalEventData->type->name }}</span>
            </li>
        @endif
    </ul>
@endif
