<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Module;

use ModularitySimpleviewEvents\Query\UpcomingEventsQuery;

/**
 * Lists the next four upcoming events from a synced Simpleview channel.
 */
class UpcomingEvents extends \Modularity\Module
{
    public $slug = 'sv-events';

    public $supports = [];

    public function init(): void
    {
        $this->nameSingular = __('Simpleview evenemang', 'modularity-simpleview-events');
        $this->namePlural = __('Simpleview evenemang', 'modularity-simpleview-events');
        $this->description = __(
            'Visar de fyra närmaste kommande evenemangen från en synkad Simpleview-kanal.',
            'modularity-simpleview-events'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $fields = $this->getFields();
        $postType = is_string($fields['post_type'] ?? null) ? $fields['post_type'] : '';
        $calendarPageId = $fields['calendar_page'] ?? null;
        $calendarUrl = '';

        if (is_object($calendarPageId) && isset($calendarPageId->ID)) {
            $calendarUrl = (string) get_permalink((int) $calendarPageId->ID);
        } elseif (is_numeric($calendarPageId) && (int) $calendarPageId > 0) {
            $calendarUrl = (string) get_permalink((int) $calendarPageId);
        }

        $events = $postType !== '' ? (new UpcomingEventsQuery())->getUpcomingEvents($postType) : [];

        return [
            'events' => $events,
            'calendarUrl' => $calendarUrl,
            'iconColor' => '#666666',
            'emptyMessage' => __('Inga kommande evenemang.', 'modularity-simpleview-events'),
        ];
    }

    public function template(): string
    {
        return 'sv-events.blade.php';
    }
}
