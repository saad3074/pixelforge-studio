<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Services\Credits\CreditLedger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public const PACK_NANOS = 10_000_000_000; // $10.00

    public function index(Request $request): Response
    {
        $transactions = CreditTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (CreditTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'amount' => $tx->amount,
                'amount_display' => Money::formatDisplay($tx->amount),
                'balance_after' => $tx->balance_after,
                'balance_after_display' => Money::formatDisplay($tx->balance_after),
                'generation_id' => $tx->generation_id,
                'created_at' => $tx->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Credits', [
            'balance' => $request->user()->credit_balance,
            'balance_display' => Money::formatDisplay($request->user()->credit_balance),
            'pack_amount' => self::PACK_NANOS,
            'pack_display' => Money::formatDisplay(self::PACK_NANOS),
            'transactions' => $transactions,
        ]);
    }

    public function purchase(Request $request, CreditLedger $ledger): RedirectResponse
    {
        $key = (string) ($request->input('idempotency_key') ?: Str::uuid());

        $ledger->purchase($request->user(), self::PACK_NANOS, $key);

        return redirect()
            ->route('credits.index')
            ->with('success', 'Added $10.00 of credits.');
    }
}
