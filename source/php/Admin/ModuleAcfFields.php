<?php

declare(strict_types=1);

namespace ModularitySimpleviewEvents\Admin;

use ModularitySimpleviewEvents\PostType\DynamicPostTypeManager;

/**
 * Dynamic ACF choices for the Simpleview events listing module.
 */
class ModuleAcfFields
{
    public function __construct()
    {
        add_filter('acf/load_field/key=field_sv_events_post_type', [$this, 'loadPostTypeChoices']);
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function loadPostTypeChoices(array $field): array
    {
        $registered = (new DynamicPostTypeManager())->getRegisteredPostTypes();
        $choices = [];

        foreach ($registered as $slug => $info) {
            if (!is_string($slug) || !is_array($info)) {
                continue;
            }

            $choices[$slug] = is_string($info['name'] ?? null) && $info['name'] !== ''
                ? $info['name']
                : $slug;
        }

        asort($choices, SORT_NATURAL | SORT_FLAG_CASE);
        $field['choices'] = $choices;

        return $field;
    }
}
