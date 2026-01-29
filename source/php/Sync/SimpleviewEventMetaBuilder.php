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

        $startDate = $this->computeEarliestStartDate($normalizedSchedules);
        if ($startDate !== null) {
            $meta['start_date'] = $startDate; // Municipio-compatible key/format
        } else {
            // Do not block sync; just log a warning for diagnostics.
            error_log(sprintf(
                'Simpleview Events: Could not compute start_date for product %s',
                (string) ($product['@id'] ?? $product['id'] ?? 'unknown')
            ));
        }

        $location = $this->computeLocationNameForEarliest($normalizedSchedules);
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
     * Compute earliest overall start date as "Y-m-d H:i:s" in Europe/Stockholm.
     *
     * Treat missing time as 00:00:00.
     *
     * @param array<int, array<string, mixed>> $schedules
     */
    private function computeEarliestStartDate(array $schedules): ?string
    {
        $earliest = null;
        $tz = new \DateTimeZone(self::TIMEZONE);

        foreach ($schedules as $schedule) {
            if (!is_array($schedule)) {
                continue;
            }

            $date = $schedule['fromDate']['@date'] ?? $schedule['fromDate']['#text'] ?? null;
            if (!is_string($date) || $date === '') {
                continue;
            }

            $time =
                $schedule['day']['fromTime']['@time']
                ?? $schedule['day']['fromTime']['#text']
                ?? null;

            // Treat start-only events as having no end time; if time is missing, default to midnight.
            if (!is_string($time) || $time === '') {
                $time = '00:00:00';
            } elseif (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
                $time .= ':00';
            }

            // Safe parse: create from explicit format.
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, $tz);
            if ($dt === false) {
                continue;
            }

            if ($earliest === null || $dt < $earliest) {
                $earliest = $dt;
            }
        }

        return $earliest ? $earliest->format('Y-m-d H:i:s') : null;
    }

    /**
     * Pick location name from the earliest schedule (if present).
     *
     * @param array<int, array<string, mixed>> $schedules
     */
    private function computeLocationNameForEarliest(array $schedules): ?string
    {
        if (empty($schedules)) {
            return null;
        }

        $tz = new \DateTimeZone(self::TIMEZONE);
        $best = null;
        $bestLocation = null;

        foreach ($schedules as $schedule) {
            if (!is_array($schedule)) {
                continue;
            }

            $date = $schedule['fromDate']['@date'] ?? $schedule['fromDate']['#text'] ?? null;
            if (!is_string($date) || $date === '') {
                continue;
            }

            $time =
                $schedule['day']['fromTime']['@time']
                ?? $schedule['day']['fromTime']['#text']
                ?? '00:00:00';

            if (is_string($time) && preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
                $time .= ':00';
            }
            if (!is_string($time) || $time === '') {
                $time = '00:00:00';
            }

            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, $tz);
            if ($dt === false) {
                continue;
            }

            if ($best === null || $dt < $best) {
                $best = $dt;
                $loc = $schedule['location'] ?? null;
                $bestLocation = is_string($loc) ? trim($loc) : null;
            }
        }

        return $bestLocation ?: null;
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

        // Fallback: look for textList entries of type Organizer/Organizer_HTML.
        $texts = $product['textList']['text'] ?? null;
        if (!is_array($texts)) {
            return null;
        }

        // Handle both array and single object
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
}

