<?php

if (function_exists('acf_add_local_field_group')) {
    acf_add_local_field_group([
        'key' => 'group_sv_events_module',
        'title' => __('Simpleview evenemang', 'modularity-simpleview-events'),
        'fields' => [
            [
                'key' => 'field_sv_events_post_type',
                'label' => __('Kanal', 'modularity-simpleview-events'),
                'name' => 'post_type',
                'aria-label' => '',
                'type' => 'select',
                'instructions' => __('Välj vilken synkad Simpleview-kanal som ska visas.', 'modularity-simpleview-events'),
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'choices' => [],
                'default_value' => false,
                'return_format' => 'value',
                'multiple' => 0,
                'allow_null' => 0,
                'ui' => 1,
                'ajax' => 0,
                'placeholder' => '',
            ],
            [
                'key' => 'field_sv_events_calendar_link_label',
                'label' => __('Länktext', 'modularity-simpleview-events'),
                'name' => 'calendar_link_label',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => __('Text för länken till kalendersidan. Standard är "Till evenemangskalendern".', 'modularity-simpleview-events'),
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '50',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => 'Till evenemangskalendern',
                'maxlength' => '',
                'placeholder' => 'Till evenemangskalendern',
                'prepend' => '',
                'append' => '',
            ],
            [
                'key' => 'field_sv_events_calendar_page',
                'label' => __('Kalendersida', 'modularity-simpleview-events'),
                'name' => 'calendar_page',
                'aria-label' => '',
                'type' => 'post_object',
                'instructions' => __('Sida som "visa alla"-länken ska gå till. Lämna tom för att dölja länken.', 'modularity-simpleview-events'),
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '50',
                    'class' => '',
                    'id' => '',
                ],
                'post_type' => [
                    'page',
                ],
                'post_status' => [
                    'publish',
                ],
                'taxonomy' => '',
                'return_format' => 'id',
                'multiple' => 0,
                'allow_null' => 1,
                'ui' => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'mod-sv-events',
                ],
            ],
            [
                [
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/sv-events',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ]);
}
