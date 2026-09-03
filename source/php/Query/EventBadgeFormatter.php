<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Query;

/**
 * Formats short badge dates (e.g. "21 mar") matching Latest Events cards.
 */
class EventBadgeFormatter
{
    /**
     * @var array<string, string>
     */
    private const MONTHS = [
        'januari' => 'jan',
        'februari' => 'feb',
        'mars' => 'mar',
        'april' => 'apr',
        'maj' => 'maj',
        'juni' => 'jun',
        'juli' => 'jul',
        'augusti' => 'aug',
        'september' => 'sep',
        'oktober' => 'okt',
        'november' => 'nov',
        'december' => 'dec',
    ];

    public static function fromTimestamp(?int $timestamp): string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return '';
        }

        $day = date_i18n('j', $timestamp);
        $monthFull = mb_strtolower(date_i18n('F', $timestamp), 'UTF-8');
        $shortMonth = self::MONTHS[$monthFull] ?? mb_substr($monthFull, 0, 3, 'UTF-8');

        return trim($day . ' ' . $shortMonth);
    }
}
