<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final readonly class DashboardStatisticsPeriod
{
    private function __construct(
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $endExclusive,
        public ?string $selectedPeriod,
    ) {}

    public static function allTime(): self
    {
        return new self(null, null, null);
    }

    public static function today(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfDay();
        $end = $start->addDay();

        return new self($start, $end, 'today');
    }

    public static function last7Days(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfDay()->subDays(6);
        $end = CarbonImmutable::now($tz)->startOfDay()->addDay();

        return new self($start, $end, 'last_7_days');
    }

    public static function last30Days(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfDay()->subDays(29);
        $end = CarbonImmutable::now($tz)->startOfDay()->addDay();

        return new self($start, $end, 'last_30_days');
    }

    public static function thisMonth(): self
    {
        $tz = config('app.timezone');
        $start = CarbonImmutable::now($tz)->startOfMonth();
        $end = CarbonImmutable::now($tz)->startOfDay()->addDay();

        return new self($start, $end, 'this_month');
    }

    public static function custom(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $start = $from->startOfDay();
        $end = $to->startOfDay()->addDay();

        return new self($start, $end, 'custom');
    }

    public static function fromRequest(Request $request): self
    {
        $period = $request->input('period');

        return match ($period) {
            null => self::allTime(),
            'today' => self::today(),
            'last_7_days' => self::last7Days(),
            'last_30_days' => self::last30Days(),
            'this_month' => self::thisMonth(),
            'custom' => self::custom(
                CarbonImmutable::parse($request->input('from'), config('app.timezone')),
                CarbonImmutable::parse($request->input('to'), config('app.timezone')),
            ),
            default => self::allTime(),
        };
    }

    public function isAllTime(): bool
    {
        return $this->start === null && $this->endExclusive === null;
    }
}
