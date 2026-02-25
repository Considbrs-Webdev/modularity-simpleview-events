<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Sync;

/**
 * Builds post meta from a Simpleview product payload.
 *
 * Goal:
 * - Provide a single canonical `start_date` meta (Y-m-d H:i:s, Europe/Stockholm)
 *   to enable archive sorting + Municipio "date range" filtering.
 * - Treat events as start-only (no end time).
 * - Choose earliest overall start across schedules.
 * - Store normalized schedule payload for future extension/debugging.
 */
class SimpleviewEventMetaBuilder
{
    private const TIMEZONE = 'Europe/Stockholm';

    /**
     * Build meta_input additions for a post.
     *
     * @param array $product Simpleview product array
     * @return array<string, mixed>
     */
    public function buildMeta(array $product): array
    {
        $meta = [];

        $normalizedSchedules = $this->normalizeSchedules($product);
        if (!empty($normalizedSchedules)) {
            $meta['simpleview_schedule_json'] = wp_json_encode($normalizedSchedules, JSON_UNESCAPED_UNICODE);
        }

        $earliestSchedule = $this->pickEarliestSchedule($normalizedSchedules);
        $startDateTime = $earliestSchedule ? $this->getScheduleStartDateTime($earliestSchedule) : null;
        
        if ($startDateTime !== null) {
            $meta['start_date'] = $startDateTime->format('Y-m-d H:i:s');
            $meta['start_date_timestamp'] = $startDateTime->getTimestamp();
        } else {
            error_log(sprintf(
                'Simpleview Events: Could not compute start_date for product %s',
                (string) ($product['@id'] ?? $product['id'] ?? 'unknown')
            ));
        }

        if ($earliestSchedule) {
            $endDate = $this->computeEndDateFromSchedule($earliestSchedule);
            if ($endDate !== null) {
                $meta['simpleview_event_end_date'] = $endDate;
            }
        }

        $location = $earliestSchedule ? $this->extractLocationFromSchedule($earliestSchedule) : null;
        if ($location === null) {
            $location = $this->fallbackLocationFromProduct($product);
        }
        if ($location !== null && $location !== '') {
            $meta['simpleview_event_location_name'] = $location;
        }

        $organiser = $this->fallbackOrganiserFromProduct($product);
        if ($organiser !== null && $organiser !== '') {
            $meta['simpleview_event_organiser'] = $organiser;
        }

        $image = $this->computeImageFromProduct($product);
        if (!empty($image)) {
            $meta['simpleview_event_image_json'] = wp_json_encode($image, JSON_UNESCAPED_UNICODE);
        }

        return $meta;
    }

    /**
     * Normalize scheduleList.schedule into a consistent array of associative arrays.
     *
     * @param array $product
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSchedules(array $product): array
    {
        $schedule = $product['scheduleList']['schedule'] ?? null;
        if ($schedule === null) {
            return [];
        }

        // Handle both single object and array
        if (is_array($schedule) && isset($schedule[0])) {
            return array_values(array_filter($schedule, 'is_array'));
        }

        return is_array($schedule) ? [$schedule] : [];
    }

    /**
     * Pick the schedule with the earliest overall start.
     *
     * @param array<int, array<string, mixed>> $schedules
     * @return array<string, mixed>|null
     */
    private function pickEarliestSchedule(array $schedules): ?array
    {
        $best = null;
        $bestSchedule = null;
        $tz = new \DateTimeZone(self::TIMEZONE);

        foreach ($schedules as $schedule) {
            if (!is_array($schedule)) {
                continue;
            }

            $dt = $this->parseScheduleStart($schedule, $tz);
            if ($dt === null) {
                continue;
            }

            if ($best === null || $dt < $best) {
                $best = $dt;
                $bestSchedule = $schedule;
            }
        }

        return $bestSchedule;
    }

    private function getScheduleStartDateTime(array $schedule): ?\DateTimeImmutable
    {
        $tz = new \DateTimeZone(self::TIMEZONE);
        return $this->parseScheduleStart($schedule, $tz);
    }

    /**
     * Compute end datetime only if toTime exists.
     */
    private function computeEndDateFromSchedule(array $schedule): ?string
    {
        $toTime = $schedule['day']['toTime']['@time'] ?? $schedule['day']['toTime']['#text'] ?? null;
        if (!is_string($toTime) || trim($toTime) === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $toTime) === 1) {
            $toTime .= ':00';
        }

        $toDate = $schedule['toDate']['@date'] ?? $schedule['toDate']['#text'] ?? null;
        if (!is_string($toDate) || trim($toDate) === '') {
            $toDate = $schedule['fromDate']['@date'] ?? $schedule['fromDate']['#text'] ?? null;
        }
        if (!is_string($toDate) || trim($toDate) === '') {
            return null;
        }

        $tz = new \DateTimeZone(self::TIMEZONE);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($toDate) . ' ' . trim($toTime), $tz);
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    private function extractLocationFromSchedule(array $schedule): ?string
    {
        $loc = $schedule['location'] ?? null;
        return is_string($loc) && trim($loc) !== '' ? trim($loc) : null;
    }

    private function parseScheduleStart(array $schedule, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $date = $schedule['fromDate']['@date'] ?? $schedule['fromDate']['#text'] ?? null;
        if (!is_string($date) || trim($date) === '') {
            return null;
        }

        $time =
            $schedule['day']['fromTime']['@time']
            ?? $schedule['day']['fromTime']['#text']
            ?? null;

        if (!is_string($time) || trim($time) === '') {
            $time = '00:00:00';
        } elseif (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($date) . ' ' . trim($time), $tz);
        return $dt ?: null;
    }

    private function fallbackLocationFromProduct(array $product): ?string
    {
        $street = $product['address']['street'] ?? null;
        $postalArea = $product['address']['postalArea']['#text'] ?? null;

        $parts = [];
        if (is_string($street) && trim($street) !== '') {
            $parts[] = trim($street);
        }
        if (is_string($postalArea) && trim($postalArea) !== '') {
            $parts[] = trim($postalArea);
        }

        if (!empty($parts)) {
            return implode(', ', $parts);
        }

        return null;
    }

    private function fallbackOrganiserFromProduct(array $product): ?string
    {
        $organiser = $product['eventOrganiser'] ?? null;
        if (is_string($organiser) && trim($organiser) !== '') {
            return trim($organiser);
        }

        $texts = $product['textList']['text'] ?? null;
        if (!is_array($texts)) {
            return null;
        }

        $items = isset($texts[0]) ? $texts : [$texts];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = $item['@type'] ?? null;
            if ($type === 'Organizer' || $type === 'Organizer_HTML') {
                $value = $item['#text'] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }

    /**
     * Compute image payload from Simpleview mediaList.
     *
     * @return array{src?:string,alt?:string,srcset?:string,sizes?:string,variants?:array<string,array{src?:string,width?:int,height?:int}>}
     */
    private function computeImageFromProduct(array $product): array
    {
        $media = $product['mediaList']['media'] ?? null;
        if ($media === null) {
            return [];
        }

        $items = [];
        if (is_array($media) && isset($media[0])) {
            $items = array_values(array_filter($media, 'is_array'));
        } elseif (is_array($media)) {
            $items = [$media];
        }

        if (empty($items)) {
            return [];
        }

        $selected = $items[0];
        foreach ($items as $item) {
            $seq = $item['@sequence'] ?? null;
            if ($seq === '1' || $seq === 1) {
                $selected = $item;
                break;
            }
        }

        $variants = [];
        foreach (['thumbnail', 'small', 'large'] as $key) {
            $node = $selected[$key] ?? null;
            if (!is_array($node)) {
                continue;
            }

            $src = $node['@URI'] ?? null;
            if (!is_string($src) || trim($src) === '') {
                continue;
            }

            $variants[$key] = [
                'src' => trim($src),
                'width' => isset($node['@width']) ? (int) $node['@width'] : null,
                'height' => isset($node['@height']) ? (int) $node['@height'] : null,
            ];
        }

        if (empty($variants)) {
            return [];
        }

        $src =
            ($variants['large']['src'] ?? null)
            ?? ($variants['small']['src'] ?? null)
            ?? ($variants['thumbnail']['src'] ?? null);

        $alt = $selected['description'] ?? ($product['name'] ?? null);
        $alt = is_string($alt) ? trim($alt) : null;

        $srcsetParts = [];
        $sortable = [];
        foreach ($variants as $v) {
            if (!empty($v['src']) && !empty($v['width'])) {
                $sortable[] = $v;
            }
        }
        usort($sortable, static fn($a, $b) => ($a['width'] ?? 0) <=> ($b['width'] ?? 0));
        foreach ($sortable as $v) {
            $srcsetParts[] = $v['src'] . ' ' . (int) $v['width'] . 'w';
        }

        $image = [
            'src' => $src,
            'alt' => $alt ?: '',
            'variants' => $variants,
        ];

        if (!empty($srcsetParts)) {
            $image['srcset'] = implode(', ', $srcsetParts);
            $image['sizes'] = '(max-width: 768px) 100vw, 630px';
        }

        return $image;
    }

}
