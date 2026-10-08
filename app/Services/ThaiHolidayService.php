<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ThaiHolidayService
{
    /** Inclusive working days, using the same calendar for manual and Excel leave. */
    public function workingDaysBetween(string $startDate, string $endDate): int
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $endDate);
        } catch (Throwable) {
            return 0;
        }
        if (! $start || ! $end || $start->gt($end) || $start->diffInDays($end) > 366) {
            return 0;
        }
        $holidays = $this->dateKeysBetween($start, $end);
        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $days += $date->isWeekend() || isset($holidays[$date->toDateString()]) ? 0 : 1;
        }

        return $days;
    }

    private const ICS_URL = 'https://calendar.google.com/calendar/ical/en.th%23holiday%40group.v.calendar.google.com/public/basic.ics';

    private const CACHE_KEY = 'thai-public-holidays.ics.v1';

    /**
     * Fixed-date fallback holidays. Google Calendar remains the source of
     * truth for lunar and substitute holidays, while this keeps common public
     * holidays excluded if the calendar is temporarily unreachable.
     *
     * @var list<string>
     */
    private const FALLBACK_DATES = [
        '2025-01-01', '2025-04-06', '2025-04-13', '2025-04-14', '2025-04-15',
        '2025-05-01', '2025-05-04', '2025-06-03', '2025-07-28', '2025-08-12',
        '2025-10-13', '2025-10-23', '2025-12-05', '2025-12-10', '2025-12-31',
        '2026-01-01', '2026-04-06', '2026-04-13', '2026-04-14', '2026-04-15',
        '2026-05-01', '2026-05-04', '2026-06-03', '2026-07-28', '2026-08-12',
        '2026-10-13', '2026-10-23', '2026-12-05', '2026-12-10', '2026-12-31',
        '2027-01-01', '2027-04-06', '2027-04-13', '2027-04-14', '2027-04-15',
        '2027-05-01', '2027-05-04', '2027-06-03', '2027-07-28', '2027-08-12',
        '2027-10-13', '2027-10-23', '2027-12-05', '2027-12-10', '2027-12-31',
    ];

    /** @return array<string, true> */
    public function dateKeysBetween(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($start->gt($end)) {
            return [];
        }

        $holidays = [];
        foreach ($this->holidayDateKeys() as $date) {
            if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                $holidays[$date] = true;
            }
        }

        return $holidays;
    }

    /** @return list<string> */
    private function holidayDateKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), function (): array {
            try {
                $response = Http::accept('text/calendar')
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->get(self::ICS_URL);

                if (! $response->successful()) {
                    return self::FALLBACK_DATES;
                }

                $dates = $this->parseIcsDates($response->body());

                return $dates === [] ? self::FALLBACK_DATES : $dates;
            } catch (Throwable) {
                return self::FALLBACK_DATES;
            }
        });
    }

    /** @return list<string> */
    private function parseIcsDates(string $ics): array
    {
        $ics = preg_replace('/\r?\n[ \t]/', '', $ics) ?? $ics;
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $events);

        $dates = [];
        foreach ($events[1] ?? [] as $event) {
            if (preg_match('/^DTSTART(?:;[^:]+)?:([0-9]{8})/m', $event, $match) !== 1) {
                continue;
            }

            try {
                $dates[] = CarbonImmutable::createFromFormat('!Ymd', $match[1])->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        return array_values(array_unique($dates));
    }
}
