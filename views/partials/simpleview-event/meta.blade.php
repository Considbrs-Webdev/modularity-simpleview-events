@if (!empty($post->simpleviewEventData))
    <ul class="c-simpleview-event-single__meta unlist">
        {{-- Media channel --}}
        @if (!empty($post->simpleviewEventData->mediaChannelName))
            <li class="c-simpleview-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-calendar'])
                @endicon
                <span>{{ $post->simpleviewEventData->mediaChannelName }}</span>
            </li>
        @endif

        {{-- Simpleview ID --}}
        @if (!empty($post->simpleviewEventData->simpleviewId))
            <li class="c-simpleview-event-single__meta-item">
                @icon(['icon' => 'fa-solid fa-hashtag'])
                @endicon
                <span>{{ $post->simpleviewEventData->simpleviewId }}</span>
            </li>
        @endif
    </ul>
@endif
