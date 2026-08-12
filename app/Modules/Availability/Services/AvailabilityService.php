<?php

namespace App\Modules\Availability\Services;

use App\Modules\Availability\DTO\AvailabilityCheckResult;
use App\Modules\Availability\Models\AvailabilityRule;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AvailabilityService
{
    public function check(FishingSpot $spot, CarbonInterface $startsAt, CarbonInterface $endsAt): AvailabilityCheckResult
    {
        $startsAt = CarbonImmutable::instance($startsAt);
        $endsAt = CarbonImmutable::instance($endsAt);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return new AvailabilityCheckResult(false, 'invalid_range');
        }

        if ($spot->status !== FishingSpotStatus::Active) {
            return new AvailabilityCheckResult(false, 'inactive_spot');
        }

        $durationMinutes = $this->durationMinutes($startsAt, $endsAt);

        if (! $this->isCoveredByAvailabilityRules($spot, $startsAt, $endsAt, $durationMinutes)) {
            return new AvailabilityCheckResult(false, 'outside_availability_rules');
        }

        if ($this->hasOverlappingBlock($spot, $startsAt, $endsAt)) {
            return new AvailabilityCheckResult(false, 'blocked');
        }

        if ($this->hasOverlappingReservation($spot, $startsAt, $endsAt)) {
            return new AvailabilityCheckResult(false, 'reserved');
        }

        return new AvailabilityCheckResult(true);
    }

    private function durationMinutes(CarbonImmutable $startsAt, CarbonImmutable $endsAt): int
    {
        return (int) ceil(($endsAt->getTimestamp() - $startsAt->getTimestamp()) / 60);
    }

    private function hasOverlappingBlock(FishingSpot $spot, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $spot->availabilityBlocks()
            ->where('starts_at', '<', $endsAt->toDateTimeString())
            ->where('ends_at', '>', $startsAt->toDateTimeString())
            ->exists();
    }

    private function hasOverlappingReservation(FishingSpot $spot, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $spot->spotReservations()
            ->where('starts_at', '<', $endsAt->toDateTimeString())
            ->where('ends_at', '>', $startsAt->toDateTimeString())
            ->where(function ($query): void {
                $query
                    ->where('status', ReservationStatus::Confirmed->value)
                    ->orWhere(function ($query): void {
                        $query
                            ->where('status', ReservationStatus::Hold->value)
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('hold_expires_at')
                                    ->orWhere('hold_expires_at', '>', now()->toDateTimeString());
                            });
                    });
            })
            ->exists();
    }

    private function isCoveredByAvailabilityRules(
        FishingSpot $spot,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $durationMinutes
    ): bool {
        $cursor = $startsAt;

        while ($cursor->lessThan($endsAt)) {
            $segmentEnd = $cursor->endOfDay()->addSecond();

            if ($segmentEnd->greaterThan($endsAt)) {
                $segmentEnd = $endsAt;
            }

            if (! $this->isSegmentCovered($spot, $cursor, $segmentEnd, $durationMinutes)) {
                return false;
            }

            $cursor = $segmentEnd;
        }

        return true;
    }

    private function isSegmentCovered(
        FishingSpot $spot,
        CarbonImmutable $segmentStartsAt,
        CarbonImmutable $segmentEndsAt,
        int $durationMinutes
    ): bool {
        foreach ([$segmentStartsAt->startOfDay(), $segmentStartsAt->subDay()->startOfDay()] as $ruleDate) {
            $rules = $spot->availabilityRules()
                ->where('day_of_week', $ruleDate->isoWeekday())
                ->where(function ($query) use ($ruleDate): void {
                    $query
                        ->whereNull('valid_from')
                        ->orWhere('valid_from', '<=', $ruleDate->toDateString());
                })
                ->where(function ($query) use ($ruleDate): void {
                    $query
                        ->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', $ruleDate->toDateString());
                })
                ->where('minimum_booking_minutes', '<=', $durationMinutes)
                ->where(function ($query) use ($durationMinutes): void {
                    $query
                        ->whereNull('maximum_booking_minutes')
                        ->orWhere('maximum_booking_minutes', '>=', $durationMinutes);
                })
                ->get();

            if ($rules->contains(fn (AvailabilityRule $rule): bool => $this->ruleCoversSegment($rule, $ruleDate, $segmentStartsAt, $segmentEndsAt))) {
                return true;
            }
        }

        return false;
    }

    private function ruleCoversSegment(
        AvailabilityRule $rule,
        CarbonImmutable $ruleDate,
        CarbonImmutable $segmentStartsAt,
        CarbonImmutable $segmentEndsAt
    ): bool {
        $opensAt = $this->dateWithTime($ruleDate, $rule->opens_at);
        $closesAt = $this->dateWithTime($ruleDate, $rule->closes_at);

        if ($closesAt->equalTo($opensAt)) {
            $closesAt = $opensAt->addDay();
        } elseif ($closesAt->lessThan($opensAt)) {
            $closesAt = $closesAt->addDay();
        }

        return $segmentStartsAt->greaterThanOrEqualTo($opensAt)
            && $segmentEndsAt->lessThanOrEqualTo($closesAt);
    }

    private function dateWithTime(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$hours, $minutes, $seconds] = array_pad(array_map('intval', explode(':', $time)), 3, 0);

        return $date->setTime($hours, $minutes, $seconds);
    }
}
