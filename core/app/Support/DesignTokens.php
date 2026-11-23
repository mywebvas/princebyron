<?php

namespace App\Support;

/**
 * DesignTokens — Centralized token generator and validator.
 * 
 * Generates core/resources/css/tokens.css directly from config/tokens.php,
 * ensuring all color literals exist in exactly ONE source of truth.
 */
class DesignTokens
{
    public static function generateCss(): string
    {
        $tokens = config('tokens');
        if (! $tokens) {
            $tokens = require __DIR__ . '/../../config/tokens.php';
        }

        $css = "/* ==========================================================================\n";
        $css .= "   PRINCE BYRON — COMPILED DESIGN TOKENS\n";
        $css .= "   AUTO-GENERATED FROM config/tokens.php — DO NOT EDIT DIRECTLY\n";
        $css .= "   ========================================================================== */\n\n";

        $css .= ":root {\n";

        // 1. Primitive Palette
        $css .= "  /* Primitive Palette */\n";
        foreach ($tokens['primitives'] as $key => $value) {
            $prop = str_replace('_', '-', $key);
            $css .= "  --color-{$prop}: {$value};\n";
        }

        // 2. Semantic Surfaces
        $css .= "\n  /* Semantic Surfaces */\n";
        foreach ($tokens['semantic'] as $key => $value) {
            if (str_starts_with($key, 'surface_')) {
                $prop = str_replace('_', '-', $key);
                $css .= "  --{$prop}: {$value};\n";
            }
        }

        // 3. Semantic Text
        $css .= "\n  /* Semantic Text */\n";
        foreach ($tokens['semantic'] as $key => $value) {
            if (str_starts_with($key, 'text_')) {
                $prop = str_replace('_', '-', $key);
                $css .= "  --{$prop}: {$value};\n";
            }
        }

        // 4. Semantic Borders
        $css .= "\n  /* Semantic Borders */\n";
        foreach ($tokens['semantic'] as $key => $value) {
            if (str_starts_with($key, 'border_')) {
                $prop = str_replace('_', '-', $key);
                $css .= "  --{$prop}: {$value};\n";
            }
        }

        // 5. Semantic Actions
        $css .= "\n  /* Semantic Actions */\n";
        foreach ($tokens['semantic'] as $key => $value) {
            if (str_starts_with($key, 'action_')) {
                $prop = str_replace('_', '-', $key);
                $css .= "  --{$prop}: {$value};\n";
            }
        }

        // 6. Focus Rings
        $css .= "\n  /* Focus Rings */\n";
        foreach ($tokens['semantic'] as $key => $value) {
            if (str_starts_with($key, 'focus_')) {
                $prop = str_replace('_', '-', $key);
                $css .= "  --{$prop}: {$value};\n";
            }
        }

        // 7. Statuses
        $css .= "\n  /* Semantic Statuses */\n";
        foreach ($tokens['semantic'] as $key => $value) {
            if (str_starts_with($key, 'status_')) {
                $prop = str_replace('_', '-', $key);
                $css .= "  --{$prop}: {$value};\n";
            }
        }

        // 8. Shadows & Glows
        $css .= "\n  /* Shadows & Glows */\n";
        foreach ($tokens['shadows'] as $key => $value) {
            $prop = str_replace('_', '-', $key);
            $css .= "  --shadow-{$prop}: {$value};\n";
        }

        // 9. Radii
        $css .= "\n  /* Border Radii */\n";
        foreach ($tokens['radius'] as $key => $value) {
            $prop = str_replace('_', '-', $key);
            $css .= "  --radius-{$prop}: {$value};\n";
        }

        // 10. Transitions
        $css .= "\n  /* Transitions */\n";
        foreach ($tokens['transitions'] as $key => $value) {
            $prop = str_replace('_', '-', $key);
            $css .= "  --transition-{$prop}: {$value};\n";
        }

        // 11. Typography
        $css .= "\n  /* Typography */\n";
        foreach ($tokens['typography'] as $key => $value) {
            $prop = str_replace('_', '-', $key);
            $css .= "  --{$prop}: {$value};\n";
        }

        // Legacy compatibility aliases for existing prototype CSS references
        $css .= "\n  /* Backward Compatibility Aliases */\n";
        $css .= "  --text-main: var(--text-primary);\n";
        $css .= "  --text-on-dark: var(--text-inverse);\n";
        $css .= "  --text-muted-dark: var(--text-inverse-muted);\n";

        $css .= "}\n";

        return $css;
    }

    public static function writeCssFile(?string $path = null): void
    {
        $target = $path ?? resource_path('css/tokens.css');
        $css = self::generateCss();
        file_put_contents($target, $css);
    }
}
