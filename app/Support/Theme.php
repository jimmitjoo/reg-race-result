<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Series;
use Illuminate\Support\Facades\Storage;

/**
 * An organizer's theme (#112), and an arrangemang's on top of it, kept from year to year; an upplaga
 * without an arrangemang has its own. Logo, one or two colours, a font pair and a
 * shape. The colours an organizer picks are never used as they are: they are adjusted until text and
 * components on them meet WCAG 2.2 AA, so no choice can make a page unreadable.
 */
final class Theme
{
    /** Löpning.se's moss, the brand colour of a page without a theme. */
    public const MOSS = '#1f5c46';

    public const DEFAULTS = ['brand' => null, 'accent' => null, 'fonts' => 'lopning', 'shape' => 'soft', 'logo' => null];

    /** Curated pairs, self-hosted in public/fonts (heading, body); all read well at 16 px and have å, ä, ö. */
    public const FONTS = [
        'lopning' => ['Hanken Grotesk', 'Hanken Grotesk'],
        'modern' => ['Inter', 'Inter'],
        'sport' => ['Barlow Condensed', 'Barlow'],
        'classic' => ['Source Serif 4', 'Source Sans 3'],
        'warm' => ['Fraunces', 'Work Sans'],
        'straight' => ['Archivo', 'Archivo'],
        'friendly' => ['Nunito', 'Nunito'],
    ];

    private const FACES = [
        'Inter' => [['inter.woff2', '100 900']],
        'Barlow Condensed' => [['barlow-condensed-400.woff2', '400'], ['barlow-condensed-500.woff2', '500'], ['barlow-condensed-600.woff2', '600']],
        'Barlow' => [['barlow-400.woff2', '400'], ['barlow-500.woff2', '500'], ['barlow-600.woff2', '600']],
        'Source Serif 4' => [['source-serif-4.woff2', '200 900']],
        'Source Sans 3' => [['source-sans-3.woff2', '200 900']],
        'Fraunces' => [['fraunces.woff2', '100 900']],
        'Work Sans' => [['work-sans.woff2', '100 900']],
        'Archivo' => [['archivo.woff2', '100 900']],
        'Nunito' => [['nunito.woff2', '200 1000']],
    ];

    /** Radii for card, panel, field and pill. Soft is Löpning.se's own. */
    public const SHAPES = [
        'soft' => ['28px', '18px', '12px', '9999px'],
        'rounded' => ['16px', '12px', '10px', '12px'],
        'straight' => ['4px', '4px', '4px', '4px'],
    ];

    private const WHITE = [255, 255, 255];

    private const GROUND = [238, 240, 236];

    /** Whose theme an editor changes: an upplaga's arrangemang, so the next year looks the same, a lone upplaga, or the organizer. */
    public static function owner(Organizer $organizer, ?Event $event = null): Organizer|Event|Series
    {
        return $event?->series ?? $event ?? $organizer;
    }

    /** The theme in force: the organizer's, with what the arrangemang (or a lone upplaga) changes on top. */
    public static function for(?Organizer $organizer, ?Event $event = null, bool $draft = false): array
    {
        $own = fn ($model) => array_filter((array) ($draft ? ($model?->theme_draft ?? $model?->theme) : $model?->theme), fn ($value) => $value !== null && $value !== '');

        return array_merge(self::DEFAULTS, $own($organizer), $own($event?->series), $own($event));
    }

    /**
     * A colour fit to carry white text (4.5:1) and to stand out as a component on the ground (3:1):
     * the same hue and saturation, darkened as little as needed.
     *
     * @return array{color: string, adjusted: bool}
     */
    public static function brand(string $hex): array
    {
        [$h, $s, $l] = self::hsl(self::rgb($hex));
        $start = $l;
        // Checked on the colour as it will be written, after rounding to hex.
        $at = fn ($l) => self::rgb(self::hex(self::fromHsl($h, $s, $l)));
        while ($l > 0 && (self::contrast($at($l), self::WHITE) < 4.5 || self::contrast($at($l), self::GROUND) < 3)) {
            $l = max(0, $l - 0.005);
        }

        return ['color' => self::hex($at($l)), 'adjusted' => $l < $start];
    }

    /** A pale tint for surfaces, light enough that ink text on it stays far above AA. */
    public static function surface(string $hex): string
    {
        $rgb = self::rgb($hex);

        return self::hex(array_map(fn ($c) => (int) round(255 - (255 - $c) * 0.1), $rgb));
    }

    /** The CSS variables and font faces for a theme, for the public pages' <head> or a preview box. */
    public static function css(array $theme, string $selector = ':root'): string
    {
        $vars = [];
        if ($theme['brand']) {
            $brand = self::brand($theme['brand'])['color'];
            $surface = self::surface($theme['accent'] ?: $brand);
            // The --color-* tokens too, so that a preview box, not only :root, takes the colours.
            $vars += ['--brand' => $brand, '--brand-contrast' => '#ffffff', '--brand-surface' => $surface,
                '--color-brand' => $brand, '--color-brand-contrast' => '#ffffff', '--color-brand-surface' => $surface];
        }

        [$display, $body] = self::FONTS[$theme['fonts']] ?? self::FONTS['lopning'];
        $faces = self::faces($theme['fonts']);
        if ($theme['fonts'] !== 'lopning') {
            $vars += ['--font-sans' => "'{$body}', ui-sans-serif, system-ui, sans-serif", '--font-display' => "'{$display}', ui-sans-serif, system-ui, sans-serif"];
        }

        [$card, $panel, $field, $pill] = self::SHAPES[$theme['shape']] ?? self::SHAPES['soft'];
        if ($theme['shape'] !== 'soft') {
            $vars += ['--radius-card' => $card, '--radius-panel' => $panel, '--radius-field' => $field, '--radius-pill' => $pill];
        }

        $declarations = collect($vars)->map(fn ($value, $name) => "{$name}:{$value}")->implode(';');

        return $faces.($declarations ? "{$selector}{{$declarations}}" : '');
    }

    /** Every curated font, for the admin to show each pair in its own letters. */
    public static function allFaces(): string
    {
        return collect(array_keys(self::FONTS))->map(fn ($pair) => self::faces($pair))->implode('');
    }

    private static function faces(string $pair): string
    {
        $faces = '';
        foreach (array_unique(self::FONTS[$pair] ?? []) as $family) {
            foreach (self::FACES[$family] ?? [] as [$file, $weight]) {
                $faces .= "@font-face{font-family:'{$family}';font-style:normal;font-weight:{$weight};font-display:swap;src:url('/fonts/theme/{$file}') format('woff2')}";
            }
        }

        return $faces;
    }

    public static function logoUrl(array $theme): ?string
    {
        return $theme['logo'] ? Storage::disk('public')->url($theme['logo']) : null;
    }

    /** The logo inside the document itself, for a PDF that fetches nothing (dompdf) and a page saved offline. */
    public static function logoDataUri(array $theme): ?string
    {
        $disk = Storage::disk('public');
        if (! $theme['logo'] || ! $disk->exists($theme['logo'])) {
            return null;
        }
        $mime = str_ends_with($theme['logo'], '.svg') ? 'image/svg+xml' : $disk->mimeType($theme['logo']);

        return "data:{$mime};base64,".base64_encode($disk->get($theme['logo']));
    }

    public static function contrast(array $a, array $b): float
    {
        [$la, $lb] = [self::luminance($a), self::luminance($b)];

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return array_map('hexdec', str_split(substr(str_pad($hex, 6, '0'), 0, 6), 2));
    }

    private static function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(fn ($c) => max(0, min(255, (int) round($c))), $rgb));
    }

    private static function luminance(array $rgb): float
    {
        $linear = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private static function hsl(array $rgb): array
    {
        [$r, $g, $b] = array_map(fn ($c) => $c / 255, $rgb);
        [$max, $min] = [max($r, $g, $b), min($r, $g, $b)];
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0, 0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        } / 6;

        return [$h, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): array
    {
        if ($s == 0) {
            return [$l * 255, $l * 255, $l * 255];
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $channel = function ($t) use ($p, $q) {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);

            return match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
        };

        return [$channel($h + 1 / 3) * 255, $channel($h) * 255, $channel($h - 1 / 3) * 255];
    }
}
