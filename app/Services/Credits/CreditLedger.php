<?php

namespace App\Services\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CreditLedger
{
    public function hold(User $user, Generation $generation, int $amount): CreditTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Hold amount must be positive.');
        }

        return DB::transaction(function () use ($user, $generation, $amount) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->credit_balance < $amount) {
                throw new InsufficientCreditsException(
                    "Need at least {$amount} nanodollars; balance is {$locked->credit_balance}."
                );
            }

            $locked->credit_balance -= $amount;
            $locked->save();

            try {
                return CreditTransaction::query()->create([
                    'user_id' => $locked->id,
                    'generation_id' => $generation->id,
                    'type' => CreditTransactionType::Hold,
                    'amount' => -$amount,
                    'balance_after' => $locked->credit_balance,
                    'meta' => ['reason' => 'reserve_max_estimate'],
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new RuntimeException('Hold already exists for this generation.', 0, $e);
            }
        });
    }

    public function release(Generation $generation): ?CreditTransaction
    {
        return DB::transaction(function () use ($generation) {
            $hold = CreditTransaction::query()
                ->where('generation_id', $generation->id)
                ->where('type', CreditTransactionType::Hold)
                ->lockForUpdate()
                ->first();

            if ($hold === null) {
                return null;
            }

            if (CreditTransaction::query()
                ->where('generation_id', $generation->id)
                ->where('type', CreditTransactionType::Release)
                ->exists()) {
                return null;
            }

            $amount = abs($hold->amount);
            $user = User::query()->whereKey($hold->user_id)->lockForUpdate()->firstOrFail();
            $user->credit_balance += $amount;
            $user->save();

            try {
                return CreditTransaction::query()->create([
                    'user_id' => $user->id,
                    'generation_id' => $generation->id,
                    'type' => CreditTransactionType::Release,
                    'amount' => $amount,
                    'balance_after' => $user->credit_balance,
                    'meta' => ['reason' => 'release_hold'],
                ]);
            } catch (UniqueConstraintViolationException) {
                return null;
            }
        });
    }

    public function charge(Generation $generation, int $amount): ?CreditTransaction
    {
        if ($amount < 0) {
            throw new RuntimeException('Charge amount cannot be negative.');
        }

        return DB::transaction(function () use ($generation, $amount) {
            if (CreditTransaction::query()
                ->where('generation_id', $generation->id)
                ->where('type', CreditTransactionType::Charge)
                ->exists()) {
                return null;
            }

            $this->release($generation);

            if ($amount === 0) {
                try {
                    $user = User::query()->whereKey($generation->user_id)->lockForUpdate()->firstOrFail();

                    return CreditTransaction::query()->create([
                        'user_id' => $user->id,
                        'generation_id' => $generation->id,
                        'type' => CreditTransactionType::Charge,
                        'amount' => 0,
                        'balance_after' => $user->credit_balance,
                        'meta' => ['reason' => 'failed_or_free'],
                    ]);
                } catch (UniqueConstraintViolationException) {
                    return null;
                }
            }

            $user = User::query()->whereKey($generation->user_id)->lockForUpdate()->firstOrFail();

            if ($user->credit_balance < $amount) {
                throw new InsufficientCreditsException(
                    "Cannot charge {$amount}; balance is {$user->credit_balance}."
                );
            }

            $user->credit_balance -= $amount;
            $user->save();

            try {
                return CreditTransaction::query()->create([
                    'user_id' => $user->id,
                    'generation_id' => $generation->id,
                    'type' => CreditTransactionType::Charge,
                    'amount' => -$amount,
                    'balance_after' => $user->credit_balance,
                    'meta' => ['reason' => 'generation_succeeded'],
                ]);
            } catch (UniqueConstraintViolationException) {
                return null;
            }
        });
    }

    public function purchase(User $user, int $amount, string $idempotencyKey): CreditTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Purchase amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $idempotencyKey) {
            $existing = CreditTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->credit_balance += $amount;
            $locked->save();

            try {
                return CreditTransaction::query()->create([
                    'user_id' => $locked->id,
                    'generation_id' => null,
                    'type' => CreditTransactionType::Purchase,
                    'amount' => $amount,
                    'balance_after' => $locked->credit_balance,
                    'idempotency_key' => $idempotencyKey,
                    'meta' => ['pack' => 'fake_purchase'],
                ]);
            } catch (UniqueConstraintViolationException) {
                return CreditTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();
            }
        });
    }
}
