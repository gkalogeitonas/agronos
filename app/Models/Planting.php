<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\PlantingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One crop on one piece of land, a whole farm (no zone) or one zone, for a period (ADR-0010).
 * A missing start counts as the distant past and a missing end as the open future. The end day is exclusive:
 * a planting that ends today is already past, and the next one can start today.
 */
class Planting extends Model
{
    /** @use HasFactory<PlantingFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUS_CURRENT = 'current';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_PAST = 'past';

    protected $fillable = [
        'user_id',
        'farm_id',
        'zone_id',
        'crop_id',
        'variety',
        'started_on',
        'ended_on',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<Crop, $this>
     */
    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    /**
     * Plantings of one piece of land: the whole farm when no zone is given, otherwise that zone.
     *
     * @param  Builder<Planting>  $query
     */
    public function scopeOnLand(Builder $query, ?int $zoneId): void
    {
        $query->when($zoneId === null, fn (Builder $query) => $query->whereNull('zone_id'), fn (Builder $query) => $query->where('zone_id', $zoneId));
    }

    /**
     * Plantings that are current on the given day: started by then, and not yet ended.
     *
     * @param  Builder<Planting>  $query
     */
    public function scopeCurrentOn(Builder $query, CarbonInterface $today): void
    {
        $day = $today->toDateString();
        $query->where(fn (Builder $query) => $query->whereNull('started_on')->orWhereDate('started_on', '<=', $day))
            ->where(fn (Builder $query) => $query->whereNull('ended_on')->orWhereDate('ended_on', '>', $day));
    }

    /**
     * Whether the planting is current, upcoming or past on the given day.
     */
    public function statusOn(CarbonInterface $today): string
    {
        if ($this->started_on !== null && $this->started_on->greaterThan($today)) {
            return self::STATUS_UPCOMING;
        }

        if ($this->ended_on !== null && $this->ended_on->lessThanOrEqualTo($today)) {
            return self::STATUS_PAST;
        }

        return self::STATUS_CURRENT;
    }

    /**
     * Whether this planting's period overlaps the given one. Periods that only touch (one ends the day the next starts) do not.
     */
    public function overlaps(?CarbonInterface $startedOn, ?CarbonInterface $endedOn): bool
    {
        $startsBeforeOtherEnds = $startedOn === null || $this->ended_on === null || $startedOn->lessThan($this->ended_on);
        $otherStartsBeforeEnd = $endedOn === null || $this->started_on === null || $this->started_on->lessThan($endedOn);

        return $startsBeforeOtherEnds && $otherStartsBeforeEnd;
    }

    /**
     * Every other planting on the same piece of land (the whole farm when no zone is given, otherwise that zone)
     * that the given period would overlap. A whole-farm planting and a zone planting never clash.
     *
     * @return Collection<int, Planting>
     */
    public static function clashesWithin(Farm $farm, ?int $zoneId, ?CarbonInterface $startedOn, ?CarbonInterface $endedOn, ?Planting $ignoring = null): Collection
    {
        /** @var Collection<int, Planting> $others */
        $others = $farm->plantings()->onLand($zoneId)->with('crop')
            ->when($ignoring, fn (Builder $query) => $query->whereKeyNot($ignoring->getKey()))
            ->get();

        return $others->filter(fn (Planting $other) => $other->overlaps($startedOn, $endedOn))->values();
    }

    /**
     * Whether a new planting starting on the given day can replace this one by ending it that day:
     * this one has no end and started before that day.
     */
    public function canBeReplacedFrom(?CarbonInterface $startedOn): bool
    {
        return $startedOn !== null
            && $this->ended_on === null
            && ($this->started_on === null || $this->started_on->lessThan($startedOn));
    }

    /**
     * A short description of the planting for messages, such as "Olive (2024-03-01 to now)".
     */
    public function describePeriod(): string
    {
        $start = $this->started_on?->toDateString() ?? 'the start';
        $end = $this->ended_on?->toDateString() ?? 'now';

        return "{$this->crop->name} ({$start} to {$end})";
    }
}
