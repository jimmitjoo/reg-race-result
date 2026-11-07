<?php

namespace App\Support;

use App\Models\Organizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The organizer's own page as blocks (#115, #116): structured data per block, rendered on the server to
 * semantic HTML, never free HTML. The hero's title (the club's name unless rewritten) is the page's h1, a block's heading its h2, headings inside a
 * text h3, so the outline holds whatever order the blocks are in.
 */
final class Page
{
    public const TYPES = ['hero', 'text', 'image', 'form', 'upcoming', 'earlier', 'contact'];

    /** The looks of the hero; the full ones reach the window's edges, their content kept in the page's column. */
    public const HEROES = ['brand', 'brand-full', 'image-full', 'split', 'split-full', 'centered', 'minimal', 'image-card'];

    public const FULL_HEROES = ['brand-full', 'image-full', 'split-full', 'centered'];

    public const IMAGE_HEROES = ['image-full', 'split', 'split-full', 'image-card'];

    /** Behind a block other than the hero: the page's ground, or a band of the club's tint, its colour or a picture. */
    public const BACKGROUNDS = ['none', 'tint', 'brand', 'image'];

    /** How wide a block's content is: the text's measure, the page's column, or the window. */
    public const SIZES = ['text', 'wide', 'full'];

    /** How a block of upplagor is laid out; the next one large is for the coming ones only. */
    public const LAYOUTS = ['cards', 'list', 'feature'];

    /** The fields written straight into a block on the page, rather than in its settings. */
    public const INLINE = [
        'hero' => ['title', 'intro'],
        'text' => ['heading', 'body'],
        'image' => ['caption'],
        'upcoming' => ['heading'],
        'earlier' => ['heading'],
        'form' => ['heading', 'intro'],
        'contact' => ['heading', 'name', 'email', 'phone'],
    ];

    /** What a page nobody has edited shows: the club, what is coming and earlier years. */
    public static function default(): array
    {
        return [self::block('hero'), self::block('upcoming'), self::block('earlier')];
    }

    public static function block(string $type): array
    {
        return ['id' => Str::lower(Str::random(10)), 'type' => $type] + match ($type) {
            'hero' => ['variant' => 'brand', 'title' => '', 'intro' => '', 'image' => null, 'alt' => '', 'width' => null, 'height' => null],
            'text' => ['heading' => '', 'body' => ''],
            'image' => ['image' => null, 'alt' => '', 'caption' => '', 'width' => null, 'height' => null],
            'form' => ['heading' => '', 'intro' => '', 'form_id' => null],
            // How many: 0 is all.
            'upcoming' => ['heading' => '', 'layout' => 'cards', 'limit' => 0],
            'earlier' => ['heading' => '', 'layout' => 'cards', 'limit' => 0, 'years' => 5],
            'contact' => ['heading' => '', 'name' => '', 'email' => '', 'phone' => '', 'form_id' => null],
        } + ($type === 'hero' ? [] : [
            'size' => in_array($type, ['upcoming', 'earlier'], true) ? 'wide' : 'text',
            // Beside the block before it, sharing its row.
            'beside' => false,
            'background' => 'none',
            'background_image' => null,
        ]);
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
            "{$key}.*.title" => $text(120),
            "{$key}.*.intro" => $text(400),
            "{$key}.*.heading" => $text(120),
            "{$key}.*.body" => $text(10000),
            "{$key}.*.alt" => $text(250),
            "{$key}.*.caption" => $text(250),
            "{$key}.*.size" => ['nullable', Rule::in(self::SIZES)],
            "{$key}.*.beside" => ['nullable', 'boolean'],
            "{$key}.*.variant" => ['nullable', Rule::in(self::HEROES)],
            "{$key}.*.name" => $text(120),
            "{$key}.*.email" => ['nullable', 'email', 'max:255'],
            "{$key}.*.phone" => $text(40),
            "{$key}.*.form_id" => ['nullable', 'integer'],
            "{$key}.*.background" => ['nullable', Rule::in(self::BACKGROUNDS)],
            // Only pictures uploaded here.
            "{$key}.*.image" => ['nullable', 'string', 'regex:#^pages/[A-Za-z0-9]+\.(png|jpe?g|webp)$#'],
            "{$key}.*.background_image" => ['nullable', 'string', 'regex:#^pages/[A-Za-z0-9]+\.(png|jpe?g|webp)$#'],
            "{$key}.*.layout" => ['nullable', Rule::in(self::LAYOUTS)],
            "{$key}.*.limit" => ['nullable', 'integer', 'between:0,50'],
            "{$key}.*.years" => ['nullable', 'integer', 'between:0,50'],
        ];
    }

    /**
     * What stops publishing (#115): what would make the page inaccessible or leave a block empty,
     * each with the block it is in. $forms are the ids of the organizer's forms.
     *
     * @return list<array{id: string, problem: string}>
     */
    public static function problems(array $blocks, array $forms = []): array
    {
        $problems = [];
        foreach ($blocks as $block) {
            $blank = fn (string ...$fields) => collect($fields)->every(fn ($field) => trim((string) ($block[$field] ?? '')) === '');
            $found = match ($block['type']) {
                'hero' => [empty($block['image']) ? (in_array($block['variant'], self::IMAGE_HEROES, true) ? 'no_image' : null) : ($blank('alt') ? 'alt' : null)],
                'image' => [empty($block['image']) ? 'no_image' : ($blank('alt') ? 'alt' : null)],
                'text' => [$blank('heading', 'body') ? 'empty' : null, self::vagueLink((string) $block['body']) ? 'link_text' : null],
                'form' => [in_array((int) $block['form_id'], $forms, true) ? null : 'no_form'],
                'contact' => [$blank('name', 'email', 'phone') && ! in_array((int) $block['form_id'], $forms, true) ? 'empty' : null],
                default => [],
            };
            if (($block['background'] ?? null) === 'image' && empty($block['background_image'])) {
                $found[] = 'no_background';
            }
            foreach (array_filter($found) as $problem) {
                $problems[] = ['id' => $block['id'], 'problem' => $problem];
            }
        }

        return $problems;
    }

    /** A link that says nothing about where it goes, read out of context by a screen reader. */
    private static function vagueLink(string $body): bool
    {
        $vague = '\s*(klicka här|klicka|här|läs mer|click here|here|read more)\s*';

        return (bool) preg_match("/\\[{$vague}\\]\\(|<a\\b[^>]*>{$vague}<\\/a>/iu", $body);
    }

    /**
     * A text's body as safe HTML: paragraphs, emphasis, lists, quotes, links and its own headings as h3
     * under the block's h2, nothing else. Written in the editor as HTML, or earlier as Markdown.
     */
    public static function html(string $body): string
    {
        $html = str_starts_with(ltrim($body), '<') ? $body : Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('em')
            ->allowElement('ul')->allowElement('ol')->allowElement('li')->allowElement('blockquote')->allowElement('h3')
            ->allowElement('a', ['href'])->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])->allowRelativeLinks()
            // Kept as their text, not dropped with it.
            ->blockElement('div')->blockElement('span')->blockElement('b')->blockElement('i')->blockElement('u')->blockElement('s')
            ->blockElement('code')->blockElement('pre');

        return (new HtmlSanitizer($config))->sanitize(preg_replace('#<(/?)h[1-6]\b#i', '<$1h3', $html));
    }

    /**
     * The columns of twelve each block takes: a block beside the one before it shares its row, up to four
     * in a row; the hero always has its own.
     *
     * @return array<string, int>
     */
    public static function spans(array $blocks): array
    {
        $rows = [];
        foreach ($blocks as $at => $block) {
            $joins = $at > 0 && ! empty($block['beside']) && $block['type'] !== 'hero' && $blocks[$at - 1]['type'] !== 'hero' && count(end($rows)) < 4;
            $joins ? $rows[array_key_last($rows)][] = $block['id'] : $rows[] = [$block['id']];
        }

        return collect($rows)->flatMap(fn ($row) => array_fill_keys($row, intdiv(12, count($row))))->all();
    }

    /** The grid cell of a block that takes $span columns; on a phone every block has its own row. */
    public static function cell(int $span): string
    {
        return match ($span) {
            6 => 'col-span-12 lg:col-span-6',
            4 => 'col-span-12 lg:col-span-4',
            3 => 'col-span-12 sm:col-span-6 lg:col-span-3',
            default => 'col-span-12',
        };
    }

    /** The organizer's forms the blocks can show, by id. */
    public static function forms(Organizer $organizer): Collection
    {
        return $organizer->forms()->get()->keyBy('id');
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
