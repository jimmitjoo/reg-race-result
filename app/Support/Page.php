<?php

namespace App\Support;

use App\Models\Organizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The organizer's own page as blocks (#115, #116): structured data per block, rendered on the server to
 * semantic HTML, never free HTML. The name is the page's h1, a block's heading its h2, headings inside a
 * text h3, so the outline holds whatever order the blocks are in.
 */
final class Page
{
    public const TYPES = ['hero', 'text', 'image', 'upcoming', 'earlier', 'contact'];

    /** What a page nobody has edited shows: the club, what is coming and earlier years. */
    public static function default(): array
    {
        return [self::block('hero'), self::block('upcoming'), self::block('earlier')];
    }

    public static function block(string $type): array
    {
        return ['id' => Str::lower(Str::random(10)), 'type' => $type] + match ($type) {
            'hero' => ['intro' => '', 'image' => null, 'alt' => '', 'width' => null, 'height' => null],
            'text' => ['heading' => '', 'body' => ''],
            'image' => ['image' => null, 'alt' => '', 'caption' => '', 'size' => 'text', 'width' => null, 'height' => null],
            'upcoming', 'earlier' => ['heading' => ''],
            'contact' => ['heading' => '', 'name' => '', 'email' => '', 'phone' => ''],
        };
    }

    public static function for(Organizer $organizer, bool $draft = false): array
    {
        $blocks = ($draft ? ($organizer->page_draft ?? $organizer->page) : $organizer->page) ?? self::default();

        // Every field a block's view reads, also for blocks saved before a field was added.
        return array_values(array_map(fn ($block) => array_merge(self::block($block['type']), $block), array_filter($blocks, fn ($block) => in_array($block['type'] ?? null, self::TYPES, true))));
    }

    /** Rules for the blocks, under the key the editor binds them to. */
    public static function rules(string $key = 'blocks'): array
    {
        $text = fn (int $max) => ['nullable', 'string', "max:{$max}"];

        return [
            $key => ['array', 'max:40'],
            "{$key}.*.id" => ['required', 'string', 'max:20'],
            "{$key}.*.type" => ['required', Rule::in(self::TYPES)],
            "{$key}.*.intro" => $text(400),
            "{$key}.*.heading" => $text(120),
            "{$key}.*.body" => $text(10000),
            "{$key}.*.alt" => $text(250),
            "{$key}.*.caption" => $text(250),
            "{$key}.*.size" => ['nullable', Rule::in(['text', 'wide'])],
            "{$key}.*.name" => $text(120),
            "{$key}.*.email" => ['nullable', 'email', 'max:255'],
            "{$key}.*.phone" => $text(40),
        ];
    }

    /**
     * What stops publishing (#115): what would make the page inaccessible or leave a block empty,
     * each with the block it is in.
     *
     * @return list<array{id: string, problem: string}>
     */
    public static function problems(array $blocks): array
    {
        $problems = [];
        foreach ($blocks as $block) {
            $blank = fn (string ...$fields) => collect($fields)->every(fn ($field) => trim((string) ($block[$field] ?? '')) === '');
            $found = match ($block['type']) {
                'hero' => [! empty($block['image']) && $blank('alt') ? 'alt' : null],
                'image' => [empty($block['image']) ? 'no_image' : ($blank('alt') ? 'alt' : null)],
                'text' => [$blank('heading', 'body') ? 'empty' : null, self::vagueLink((string) $block['body']) ? 'link_text' : null],
                'contact' => [$blank('name', 'email', 'phone') ? 'empty' : null],
                default => [],
            };
            foreach (array_filter($found) as $problem) {
                $problems[] = ['id' => $block['id'], 'problem' => $problem];
            }
        }

        return $problems;
    }

    /** A link that says nothing about where it goes, read out of context by a screen reader. */
    private static function vagueLink(string $body): bool
    {
        return (bool) preg_match('/\[\s*(klicka här|klicka|här|läs mer|click here|here|read more)\s*\]\(/iu', $body);
    }

    /** Markdown without HTML or unsafe links, its headings as h3 under the block's h2. */
    public static function markdown(string $body): string
    {
        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return preg_replace('#<(/?)h[1-6]\b#', '<$1h3', $html);
    }

    /** The upplagor the blocks show: the coming ones in date order, earlier years per arrangemang. */
    public static function events(Organizer $organizer): array
    {
        $today = now($organizer->timezone)->toDateString();
        $events = $organizer->events()->with(['series', 'organizer'])->orderBy('date')->get();

        return [
            'upcoming' => $upcoming = $events->filter(fn ($event) => $event->date->toDateString() >= $today)->values(),
            'next' => $upcoming->first(fn ($event) => $event->status() !== 'cancelled'),
            // Earlier years per arrangemang, the one run most recently first.
            'earlier' => $events->filter(fn ($event) => $event->date->toDateString() < $today)
                ->groupBy(fn ($event) => $event->series?->name ?? $event->name)
                ->map->sortByDesc('date')
                ->sortByDesc(fn (Collection $years) => $years->first()->date),
        ];
    }
}
