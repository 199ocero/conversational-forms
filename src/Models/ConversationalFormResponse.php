<?php

namespace JaOcero\ConversationalForms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JaOcero\ConversationalForms\Enums\ResponseStatus;
use JaOcero\ConversationalForms\Support\Models;

/**
 * Answers given to a builder-made conversation. A response is saved as
 * partial after the first step and updated after each one, then completed
 * on submit, so drop-off can be measured.
 *
 * Old partial responses are pruned by `php artisan model:prune` (see the
 * `responses.prune_partials_after_days` config).
 *
 * @property string $id
 * @property string $conversational_form_id
 * @property string|null $conversational_form_version_id
 * @property ResponseStatus $status
 * @property array<string, mixed> $answers
 * @property array<string, mixed>|null $variables
 * @property list<string>|null $steps The steps answered, in order.
 * @property string|null $ending
 * @property array<string, mixed>|null $meta
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property-read ConversationalForm $form
 * @property-read ConversationalFormVersion|null $version
 */
class ConversationalFormResponse extends Model
{
    use HasUlids;
    use MassPrunable;

    protected $table = 'conversational_form_responses';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResponseStatus::class,
            'answers' => 'array',
            'variables' => 'array',
            'steps' => 'array',
            'meta' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ConversationalForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Models::form(), 'conversational_form_id');
    }

    /**
     * @return BelongsTo<ConversationalFormVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Models::version(), 'conversational_form_version_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === ResponseStatus::Completed;
    }

    /**
     * Seconds from the first answer to submitting, for completed responses.
     */
    public function getDurationInSeconds(): ?int
    {
        return ($this->completed_at === null) ? null : (int) $this->started_at->diffInSeconds($this->completed_at, absolute: true);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('conversational-forms.responses.prune_partials_after_days');

        if (blank($days)) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->where('status', ResponseStatus::Partial)
            ->where('updated_at', '<', now()->subDays((int) $days));
    }
}
