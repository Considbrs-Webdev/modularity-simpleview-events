<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Query;

/**
 * Keeps Simpleview archive listings from hiding posts that lack date meta.
 *
 * Municipio archive queries INNER JOIN on `start_date` / `start_date_timestamp`
 * when those Customizer fields are set. Products without a schedule never get
 * those keys, so they disappear even when no date range filter is applied.
 */
class ArchiveQueryAdjuster
{
    public const ORDER_CLAUSE = 'sv_order_meta';

    private const UNBOUNDED_DATE_FROM = '0001-01-01 00:00:00';
    private const UNBOUNDED_DATE_TO = '9999-12-31 23:59:59';

    /**
     * Adjust WP_Query vars for Simpleview post types so missing date meta does
     * not exclude posts unless the visitor applied a real date range.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function preGetPosts(\WP_Query $query): void
    {
        if (!$this->isSimpleviewPostTypeQuery($query)) {
            return;
        }

        $adjusted = $this->adjust($query->query_vars);

        if (array_key_exists('meta_query', $adjusted)) {
            $query->set('meta_query', $adjusted['meta_query']);
        }

        if (array_key_exists('orderby', $adjusted)) {
            $query->set('orderby', $adjusted['orderby']);
        }

        if (!array_key_exists('meta_key', $adjusted)) {
            unset($query->query_vars['meta_key']);
        }
    }

    /**
     * @param array<string, mixed> $queryVars
     * @return array<string, mixed>
     */
    public function adjust(array $queryVars): array
    {
        $metaQuery = $queryVars['meta_query'] ?? [];
        if (!is_array($metaQuery)) {
            $metaQuery = [];
        }

        $metaQuery = $this->includePostsMissingUnboundedDateMeta($metaQuery);
        $queryVars = $this->includePostsMissingOrderMeta($queryVars, $metaQuery);

        return $queryVars;
    }

    /**
     * @param \WP_Query $query
     * @return bool
     */
    private function isSimpleviewPostTypeQuery(\WP_Query $query): bool
    {
        $postType = $query->get('post_type');
        $types = is_array($postType) ? $postType : [$postType];
        $types = array_values(array_filter($types, 'is_string'));

        if ($types === []) {
            return false;
        }

        foreach ($types as $type) {
            if (!str_starts_with($type, 'sv_')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string|int, mixed> $metaQuery
     * @return array<string|int, mixed>
     */
    private function includePostsMissingUnboundedDateMeta(array $metaQuery): array
    {
        foreach ($metaQuery as $name => $clause) {
            if (!is_string($name) || $name === 'relation' || !is_array($clause)) {
                continue;
            }

            if (!$this->isLeafMetaClause($clause) || !$this->isUnboundedBetweenClause($clause)) {
                continue;
            }

            $key = $clause['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $missingName = $name . '_missing';
            if (isset($metaQuery[$missingName])) {
                continue;
            }

            $missingClause = [
                'key' => $key,
                'compare' => 'NOT EXISTS',
            ];

            if ($this->countLeafClauses($metaQuery) <= 1) {
                $metaQuery['relation'] = 'OR';
                $metaQuery[$missingName] = $missingClause;
                continue;
            }

            unset($metaQuery[$name]);
            $metaQuery[] = [
                'relation' => 'OR',
                $name => $clause,
                $missingName => $missingClause,
            ];
        }

        return $metaQuery;
    }

    /**
     * @param array<string, mixed> $queryVars
     * @param array<string|int, mixed> $metaQuery
     * @return array<string, mixed>
     */
    private function includePostsMissingOrderMeta(array $queryVars, array $metaQuery): array
    {
        $orderBy = $queryVars['orderby'] ?? '';
        $metaKey = $queryVars['meta_key'] ?? '';

        if (!in_array($orderBy, ['meta_value', 'meta_value_num'], true)) {
            if ($metaQuery !== []) {
                $queryVars['meta_query'] = $metaQuery;
            }
            return $queryVars;
        }

        if (!is_string($metaKey) || $metaKey === '') {
            if ($metaQuery !== []) {
                $queryVars['meta_query'] = $metaQuery;
            }
            return $queryVars;
        }

        $type = $orderBy === 'meta_value_num' ? 'NUMERIC' : $this->guessMetaType($metaKey);
        $orderClause = [
            'key' => $metaKey,
            'compare' => 'EXISTS',
            'type' => $type,
        ];
        $missingClause = [
            'key' => $metaKey,
            'compare' => 'NOT EXISTS',
        ];

        $orderGroup = [
            'relation' => 'OR',
            self::ORDER_CLAUSE => $orderClause,
            self::ORDER_CLAUSE . '_missing' => $missingClause,
        ];

        if ($metaQuery === []) {
            $queryVars['meta_query'] = $orderGroup;
        } else {
            $queryVars['meta_query'] = [
                'relation' => 'AND',
                $metaQuery,
                $orderGroup,
            ];
        }

        $order = is_string($queryVars['order'] ?? null) ? $queryVars['order'] : 'ASC';
        $queryVars['orderby'] = [self::ORDER_CLAUSE => $order];
        unset($queryVars['meta_key']);

        return $queryVars;
    }

    /**
     * @param array<string, mixed> $clause
     * @return bool
     */
    private function isLeafMetaClause(array $clause): bool
    {
        return isset($clause['key']);
    }

    /**
     * @param array<string, mixed> $clause
     * @return bool
     */
    private function isUnboundedBetweenClause(array $clause): bool
    {
        $compare = strtoupper((string) ($clause['compare'] ?? ''));
        $value = $clause['value'] ?? null;

        return $compare === 'BETWEEN'
            && is_array($value)
            && array_values($value) === [self::UNBOUNDED_DATE_FROM, self::UNBOUNDED_DATE_TO];
    }

    /**
     * @param array<string|int, mixed> $metaQuery
     * @return int
     */
    private function countLeafClauses(array $metaQuery): int
    {
        $count = 0;
        foreach ($metaQuery as $name => $clause) {
            if ($name === 'relation' || !is_array($clause) || !$this->isLeafMetaClause($clause)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function guessMetaType(string $metaKey): string
    {
        if (str_contains($metaKey, 'timestamp')) {
            return 'NUMERIC';
        }

        if ($metaKey === 'start_date' || str_ends_with($metaKey, '_date')) {
            return 'DATETIME';
        }

        return 'CHAR';
    }
}
