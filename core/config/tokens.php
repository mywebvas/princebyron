<?php

/*
|--------------------------------------------------------------------------
| Prince Byron — Centralized Color & Design Tokens
|--------------------------------------------------------------------------
|
| SINGLE SOURCE OF TRUTH for all color values, semantic roles, typography,
| borders, surfaces, and statuses across the Prince Byron platform.
|
| Raw color literals (hex/rgb/rgba) must live ONLY in this file.
| All Blade views, CSS styles, and the Filament Admin Panel derive from here.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Primitive Palette Tokens
    |--------------------------------------------------------------------------
    */
    'primitives' => [
        // Dark Foundations (Sanctuary Stage)
        'black'          => '#000000',
        'obsidian'       => '#0E0B09',
        'obsidian_deep'  => '#070504',
        'charcoal'       => '#181310',
        'charcoal_light' => '#221B16',
        'espresso'       => '#2C221C',
        'espresso_light' => '#3D3028',

        // Metallic & Worship Accents (Grace & Glory)
        'amber'          => '#C8883B',
        'amber_dark'     => '#B6772D',
        'amber_light'    => '#D8994E',
        'gold'           => '#DFA759',
        'gold_hover'     => '#ECC78D',
        'gold_muted'     => '#BA8A46',

        // Earthy Terracotta & Clay (Human Warmth)
        'clay'           => '#9E472A',
        'clay_dark'      => '#7F361E',
        'clay_light'     => '#B85332',

        // Olive & Botanical Accents
        'olive'          => '#3B4E3E',
        'olive_light'    => '#526A56',
        'olive_surface'  => '#EDF3EE',

        // Light Foundations (Editorial Sanctuary)
        'sand_dark'      => '#8E8271',
        'sand_muted'     => '#A89F91',
        'sand_soft'      => '#C5BCAD',
        'sand'           => '#EAE4D9',
        'paper'          => '#F3EFE6',
        'linen'          => '#F8F4ED',
        'cream'          => '#FCFAF6',
        'white'          => '#FFFFFF',

        // Functional Gray Scale
        'gray_50'        => '#F9F9F8',
        'gray_100'       => '#F2F0ED',
        'gray_200'       => '#E4E0D9',
        'gray_300'       => '#D0C9BF',
        'gray_400'       => '#A89F91',
        'gray_500'       => '#7E7467',
        'gray_600'       => '#5E5448',
        'gray_700'       => '#433A30',
        'gray_800'       => '#28211A',
        'gray_900'       => '#181310',

        // Status Colors
        'success'        => '#1E8E3E',
        'success_light'  => '#E6F4EA',
        'success_border' => '#A8DAB5',

        'warning'        => '#D97706',
        'warning_light'  => '#FEF3C7',
        'warning_border' => '#FCD34D',

        'error'          => '#C62828',
        'error_light'    => '#FCE8E6',
        'error_border'   => '#F5C6CB',

        'info'           => '#1A73E8',
        'info_light'     => '#E8F0FE',
        'info_border'    => '#AECBFA',
    ],

    /*
    |--------------------------------------------------------------------------
    | Semantic Tokens
    |--------------------------------------------------------------------------
    */
    'semantic' => [
        // Surfaces
        'surface_base'              => '#FCFAF6',
        'surface_paper'             => '#F3EFE6',
        'surface_card'              => '#FFFFFF',
        'surface_card_subtle'       => '#FAF7F2',
        'surface_elevated'          => '#FFFFFF',
        'surface_sunken'            => '#ECE7DC',
        'surface_overlay'           => 'rgba(14, 11, 9, 0.78)',
        'surface_overlay_deep'      => 'rgba(14, 11, 9, 0.96)',
        'surface_modal_backdrop'    => 'rgba(14, 11, 9, 0.88)',
        'surface_dark'              => '#0E0B09',
        'surface_dark_card'         => '#181310',
        'surface_dark_subtle'       => '#221B16',
        'surface_dark_sunken'       => '#070504',
        'surface_dark_translucent'  => 'rgba(24, 19, 16, 0.85)',
        'surface_dark_glass'        => 'rgba(255, 255, 255, 0.05)',
        'surface_dark_glass_hover'  => 'rgba(255, 255, 255, 0.08)',
        'surface_dark_glass_active' => 'rgba(255, 255, 255, 0.12)',
        'surface_gold_glow'         => 'rgba(223, 167, 89, 0.20)',
        'surface_amber_glow'        => 'rgba(200, 136, 59, 0.15)',
        'surface_clay_subtle'       => 'rgba(158, 71, 42, 0.06)',
        'surface_input'             => '#FFFFFF',
        'surface_input_dark'        => '#181310',

        // Text & Foreground
        'text_primary'          => '#181310',
        'text_secondary'        => '#594C44',
        'text_muted'            => '#7E7066',
        'text_subtle'           => '#A89F91',
        'text_inverse'          => '#FDFBF7',
        'text_inverse_muted'    => '#C5BCAD',
        'text_inverse_subtle'   => '#8E8271',
        'text_brand'            => '#C8883B',
        'text_gold'             => '#DFA759',
        'text_accent'           => '#9E472A',
        'text_link'             => '#C8883B',
        'text_link_hover'       => '#B6772D',

        // Borders & Dividers
        'border_subtle'         => 'rgba(24, 19, 16, 0.08)',
        'border_default'        => 'rgba(24, 19, 16, 0.14)',
        'border_strong'         => 'rgba(24, 19, 16, 0.28)',
        'border_dark_subtle'    => 'rgba(255, 255, 255, 0.08)',
        'border_dark_medium'    => 'rgba(255, 255, 255, 0.15)',
        'border_dark'           => 'rgba(200, 136, 59, 0.22)',
        'border_dark_strong'    => 'rgba(223, 167, 89, 0.45)',
        'border_gold'           => 'rgba(223, 167, 89, 0.40)',
        'border_gold_subtle'    => 'rgba(223, 167, 89, 0.20)',
        'border_gold_strong'    => '#DFA759',
        'border_clay'           => 'rgba(158, 71, 42, 0.35)',
        'border_focus'          => '#9E472A',
        'border_input'          => '#8A7D71',
        'border_input_hover'    => '#9E472A',
        'border_input_focus'    => '#9E472A',
        'border_input_dark'     => '#7E6B5A',
        'border_input_dark_hover' => '#DFA759',
        'border_input_dark_focus' => '#DFA759',

        // Action & Interactive Controls
        'action_primary_bg'         => '#C8883B',
        'action_primary_hover'      => '#B6772D',
        'action_primary_text'       => '#FFFFFF',
        'action_secondary_bg'       => 'transparent',
        'action_secondary_border'   => '#C8883B',
        'action_secondary_text'     => '#181310',
        'action_secondary_hover_bg' => 'rgba(200, 136, 59, 0.08)',

        'action_dark_primary_bg'       => '#DFA759',
        'action_dark_primary_hover'    => '#ECC78D',
        'action_dark_primary_text'     => '#0E0B09',
        'action_dark_secondary_border' => 'rgba(223, 167, 89, 0.50)',
        'action_dark_secondary_text'   => '#FDFBF7',

        // Focus Rings (WCAG 2.2 AA compliant: SC 2.4.7 Focus Visible, SC 1.4.11 Non-text Contrast >= 3:1)
        'focus_ring'            => '#9E472A',
        'focus_ring_dark'       => '#DFA759',

        // Status Feedback
        'status_success_bg'     => '#E6F4EA',
        'status_success_text'   => '#1E8E3E',
        'status_success_border' => '#A8DAB5',

        'status_warning_bg'     => '#FEF3C7',
        'status_warning_text'   => '#D97706',
        'status_warning_border' => '#FCD34D',

        'status_error_bg'       => '#FCE8E6',
        'status_error_text'     => '#C62828',
        'status_error_border'   => '#F5C6CB',

        'status_info_bg'        => '#E8F0FE',
        'status_info_text'      => '#1A73E8',
        'status_info_border'    => '#AECBFA',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Admin Panel Primary Color Scale
    |--------------------------------------------------------------------------
    */
    'filament' => [
        'primary' => [
            50  => '#FAF7EE',
            100 => '#F3EDD7',
            200 => '#E7D8A9',
            300 => '#DBC37B',
            400 => '#DFA759', // Earthy Gold
            500 => '#C98D3B', // Warm Amber
            600 => '#A86D27',
            700 => '#7D4F1B',
            800 => '#523412',
            900 => '#2B1B0A',
            950 => '#0E0B09', // Obsidian
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shadows, Radii & Transitions
    |--------------------------------------------------------------------------
    */
    'shadows' => [
        'sm'        => '0 2px 8px rgba(0, 0, 0, 0.08)',
        'md'        => '0 8px 24px rgba(0, 0, 0, 0.12)',
        'lg'        => '0 16px 40px rgba(0, 0, 0, 0.20)',
        'modal'     => '0 24px 60px rgba(0, 0, 0, 0.40)',
        'glow'              => '0 0 40px rgba(200, 136, 59, 0.20)',
        'glow_gold'         => '0 0 32px rgba(223, 167, 89, 0.30)',
        'button_subtle'     => '0 1px 3px rgba(26, 23, 20, 0.06)',
        'button_clay_hover' => '0 4px 14px rgba(184, 80, 66, 0.12)',
        'player_gold_glow'  => '0 0 24px rgba(223, 167, 89, 0.15)',
    ],

    'radius' => [
        'sm'    => '4px',
        'md'    => '8px',
        'lg'    => '16px',
        'xl'    => '24px',
        'full'  => '9999px',
    ],

    'transitions' => [
        'fast'  => '160ms cubic-bezier(0.16, 1, 0.3, 1)',
        'base'  => '280ms cubic-bezier(0.16, 1, 0.3, 1)',
        'slow'  => '450ms cubic-bezier(0.16, 1, 0.3, 1)',
    ],

    'typography' => [
        'font_serif'   => "'Cinzel', 'Playfair Display', Didot, 'Bodoni MT', 'Times New Roman', Georgia, serif",
        'font_display' => "'Cinzel', 'Playfair Display', Didot, Georgia, serif",
        'font_sans'    => "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
        'font_mono'    => "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace",
    ],
];
