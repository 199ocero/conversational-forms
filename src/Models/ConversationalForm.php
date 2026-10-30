<?php

namespace JaOcero\ConversationalForms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JaOcero\ConversationalForms\Database\Factories\ConversationalFormFactory;
use JaOcero\ConversationalForms\Definitions\ConversationDefinition;
use JaOcero\ConversationalForms\Definitions\Exceptions\InvalidDefinitionException;
use JaOcero\ConversationalForms\Enums\FormStatus;
use JaOcero\ConversationalForms\Logic\Exceptions\InvalidLogicException;
use JaOcero\ConversationalForms\Support\Models;

/**
 * A conversation made in the builder. `definition` is the draft being edited;
 * publishing copies it into an immutable version, which is what the public
 * page shows, so edits never change a live form until they are published.
 *
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property FormStatus $status
 * @property array<string, mixed> $definition
 * @property array<string, mixed>|null $theme
 * @property array<string, mixed>|null $settings
 * @property string|null $published_version_id
 * @property Carbon|null $published_at
 * @property-read ConversationalFormVersion|null $publishedVersion
 */
class ConversationalForm extends Model
{
    /** @use HasFactory<ConversationalFormFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    protected $table = 'conversational_forms';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
        'definition' => '{"schema_version":1,"steps":[]}',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'definition' => 'array',
            'theme' => 'array',
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ConversationalFormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Models::version(), 'conversational_form_id')->orderByDesc('number');
    }

    /**
     * @return HasMany<ConversationalFormResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Models::response(), 'conversational_form_id');
    }

    /**
     * @return BelongsTo<ConversationalFormVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(Models::version(), 'published_version_id');
    }

    /**
     * The draft, as edited in the builder.
     */
    public function getDraftDefinition(): ConversationDefinition
    {
        return ConversationDefinition::make($this->definition);
    }

    /**
     * Publish the draft as a new version and make it the live one.
     *
     * @throws InvalidDefinitionException
     * @throws InvalidLogicException
     */
    public function publish(): ConversationalFormVersion
    {
        $this->getDraftDefinition()->validate();

        return DB::transaction(function (): ConversationalFormVersion {
            /** @var ConversationalFormVersion $version */
            $version = $this->versions()->create([
                'number' => ((int) $this->versions()->max('number')) + 1,
                'definition' => $this->getDraftDefinition()->toArray(),
                'theme' => $this->theme,
                'published_at' => now(),
            ]);

            $this->forceFill([
                'status' => FormStatus::Published,
                'published_version_id' => $version->getKey(),
                'published_at' => $version->published_at,
            ])->save();

            $this->setRelation('publishedVersion', $version);

            return $version;
        });
    }

    /**
     * Stop accepting answers. The published versions are kept.
     */
    public function close(): void
    {
        $this->forceFill(['status' => FormStatus::Closed])->save();
    }

    /**
     * Accept answers again with the latest published version.
     */
    public function reopen(): void
    {
        $this->forceFill(['status' => ($this->published_version_id === null) ? FormStatus::Draft : FormStatus::Published])->save();
    }

    public function isPublished(): bool
    {
        return ($this->status === FormStatus::Published) && ($this->published_version_id !== null);
    }

    /**
     * Whether the draft has changes that aren't published yet.
     */
    public function hasUnpublishedChanges(): bool
    {
        $version = $this->publishedVersion;

        return ($version === null)
            || ($version->definition != $this->getDraftDefinition()->toArray())
            || ($version->theme != $this->theme);
    }

    protected static function newFactory(): ConversationalFormFactory
    {
        return ConversationalFormFactory::new();
    }
}
