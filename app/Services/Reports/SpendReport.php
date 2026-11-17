<?php

namespace App\Services\Reports;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SpendReport
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * @return array{
     *   days: list<array<string, mixed>>,
     *   users: list<array<string, mixed>>,
     *   totals: array<string, mixed>,
     *   usage_check: list<array<string, mixed>>
     * }
     */
    public function build(?Carbon $start = null, ?Carbon $end = null): array
    {
        $end = ($end ?? now('UTC'))->copy()->utc()->startOfDay();
        $start = ($start ?? $end->copy()->subDays(29))->copy()->utc()->startOfDay();

        $rows = Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereNotNull('provider_completed_at')
            ->whereBetween('provider_completed_at', [$start, $end->copy()->endOfDay()])
            ->get(['user_id', 'provider_cost', 'user_cost', 'provider_completed_at']);

        $byDay = [];
        $byUser = [];

        foreach ($rows as $row) {
            $day = $row->provider_completed_at->utc()->toDateString();
            $byDay[$day] ??= ['date' => $day, 'credit_spend' => 0, 'provider_cost' => 0, 'profit' => 0, 'generations' => 0];
            $byDay[$day]['credit_spend'] += (int) $row->user_cost;
            $byDay[$day]['provider_cost'] += (int) $row->provider_cost;
            $byDay[$day]['profit'] += (int) $row->user_cost - (int) $row->provider_cost;
            $byDay[$day]['generations']++;

            $uid = (int) $row->user_id;
            $byUser[$uid] ??= ['user_id' => $uid, 'credit_spend' => 0, 'provider_cost' => 0, 'profit' => 0, 'generations' => 0];
            $byUser[$uid]['credit_spend'] += (int) $row->user_cost;
            $byUser[$uid]['provider_cost'] += (int) $row->provider_cost;
            $byUser[$uid]['profit'] += (int) $row->user_cost - (int) $row->provider_cost;
            $byUser[$uid]['generations']++;
        }

        ksort($byDay);

        $users = User::query()->whereIn('id', array_keys($byUser))->get()->keyBy('id');
        $userRows = Collection::make($byUser)
            ->map(function (array $row) use ($users) {
                $user = $users->get($row['user_id']);

                return [
                    ...$row,
                    'name' => $user?->name,
                    'email' => $user?->email,
                    'credit_spend_display' => Money::formatDisplay($row['credit_spend']),
                    'provider_cost_display' => Money::formatDisplay($row['provider_cost']),
                    'profit_display' => Money::formatDisplay($row['profit']),
                ];
            })
            ->sortByDesc('credit_spend')
            ->values()
            ->all();

        $dayRows = Collection::make($byDay)
            ->map(fn (array $row) => [
                ...$row,
                'credit_spend_display' => Money::formatDisplay($row['credit_spend']),
                'provider_cost_display' => Money::formatDisplay($row['provider_cost']),
                'profit_display' => Money::formatDisplay($row['profit']),
            ])
            ->values()
            ->all();

        $usageCheck = [];
        try {
            $usage = $this->client->usage($start->toDateString(), $end->toDateString());
            foreach ($usage['data'] as $day) {
                $local = $byDay[$day['date']]['provider_cost'] ?? 0;
                $remote = Money::fromDecimalString((string) $day['cost_usd']);
                $usageCheck[] = [
                    'date' => $day['date'],
                    'provider_generations' => $day['generations'],
                    'local_generations' => $byDay[$day['date']]['generations'] ?? 0,
                    'provider_cost' => $remote,
                    'local_cost' => $local,
                    'provider_cost_display' => Money::formatDisplay($remote, 9),
                    'local_cost_display' => Money::formatDisplay($local, 9),
                    'matches' => $remote === $local && (int) $day['generations'] === (int) ($byDay[$day['date']]['generations'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            $usageCheck[] = [
                'date' => null,
                'error' => $e->getMessage(),
                'matches' => false,
            ];
        }

        $totals = [
            'credit_spend' => array_sum(array_column($dayRows, 'credit_spend')),
            'provider_cost' => array_sum(array_column($dayRows, 'provider_cost')),
        ];
        $totals['profit'] = $totals['credit_spend'] - $totals['provider_cost'];
        $totals['credit_spend_display'] = Money::formatDisplay($totals['credit_spend']);
        $totals['provider_cost_display'] = Money::formatDisplay($totals['provider_cost']);
        $totals['profit_display'] = Money::formatDisplay($totals['profit']);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $dayRows,
            'users' => $userRows,
            'totals' => $totals,
            'usage_check' => $usageCheck,
        ];
    }
}
