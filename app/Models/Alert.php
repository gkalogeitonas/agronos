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
}
