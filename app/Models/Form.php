<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A form the organizer builds, such as contact or volunteering, and what becomes of what people send. */
class Form extends Model
{
    protected $guarded = [];

    public const TYPES = ['text', 'email', 'phone', 'textarea', 'select', 'checkbox'];

    /** Sent as email, kept under Formulär in the admin, or both. */
    public const ACTIONS = ['both', 'email', 'store'];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    public static function field(string $type = 'text', string $label = '', bool $required = false): array
    {
        return ['id' => Str::lower(Str::random(8)), 'label' => $label, 'type' => $type, 'required' => $required, 'options' => ''];
    }

    /** A new form asks what a contact form asks. */
    public static function contactFields(): array
    {
        return [
            self::field('text', __('forms.default.name'), true),
            self::field('email', __('forms.default.email'), true),
            self::field('textarea', __('forms.default.message'), true),
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    /** Where an emailed answer goes: the form's address, or someone at the club. */
    public function recipient(): ?string
    {
        return $this->recipient ?: User::where('organizer_id', $this->organizer_id)->orderBy('id')->value('email');
    }

    /** The choices of a select field, one per comma. */
    public static function options(array $field): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($field['options'] ?? ''))), 'strlen'));
    }
}
