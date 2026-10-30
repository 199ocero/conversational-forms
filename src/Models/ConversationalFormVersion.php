<?php

namespace JaOcero\ConversationalForms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Support\Models;

/**
 * A published snapshot of a form. Versions never change, so answers can
 * always be read against the exact questions they were given to.
 *
 * @property string $id
 * @property string $conversational_form_id
 * @property int $number
 * @property array<string, mixed> $definition
 * @property array<string, mixed>|null $theme
 * @property Carbon $published_at
 * @property-read ConversationalForm $form
 */
class ConversationalFormVersion extends Model
{
    use HasUlids;

    protected $table = 'conversational_form_versions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'definition' => 'array',
            'theme' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ConversationalForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Models::form(), 'conversational_form_id');
    }

    public function getDefinition(): ConversationDefinition
    {
        return ConversationDefinition::make($this->definition);
    }
}
