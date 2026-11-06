<?php

namespace App\Models;

use App\Enums\AlertRule;
use App\Enums\AlertSeverity;
use App\Traits\BelongsToTenant;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * How many alerts a summary card lists; its tile still shows the full count.
     */
    public const CARD_LIMIT = 5;

    protected $fillable = [
        'user_id',
        'rule',
        'severity',
        'subject_type',
        'subject_id',
        'opened_at',
        'resolved_at',
        'open_marker',
    ];

    protected $casts = [
        'rule' => AlertRule::class,
        'severity' => AlertSeverity::class,
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to open alerts, ignoring any whose subject is gone. Whether the subject exists does not depend on who is signed in.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotNull('open_marker')->whereHasMorph('subject', '*', fn (Builder $subject) => $subject->withoutGlobalScopes());
    }

    /**
     * Scope to load what `AlertResource` needs to build an alert line.
     */
    public function scopeWithLineDetails(Builder $query): Builder
    {
        return $query->with(['subject' => fn (MorphTo $subject) => $subject->morphWith([Device::class => ['farm:id,name']])]);
    }

    /**
     * Scope to newest first, with the id as tiebreak since alerts of one run share a time.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('opened_at')->orderByDesc('id');
    }

    /**
     * Scope to danger first, then warning, then info, and newest first within a severity.
     */
    public function scopeMostUrgentFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE severity WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [AlertSeverity::DANGER->value, AlertSeverity::WARNING->value])
            ->newestFirst();
    }
}
