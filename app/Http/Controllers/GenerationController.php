<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Generations\GenerationCreator;
use App\Services\Pricing\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function create(Request $request, PricingService $pricing): Response
    {
        $catalog = $pricing->catalog();

        return Inertia::render('Generate', [
            'pricing' => [
                'rates' => $catalog['rates'],
                'tokens' => $catalog['tokens'],
                'markup_percent' => (int) config('services.pixelforge.markup_percent', 25),
            ],
            'balance' => $request->user()->credit_balance,
            'sizes' => ['1024x1024', '1536x1024', '1024x1536'],
            'qualities' => ['low', 'medium', 'high'],
        ]);
    }

    public function store(Request $request, GenerationCreator $creator): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::in(['1024x1024', '1536x1024', '1024x1536'])],
            'quality' => ['required', Rule::in(['low', 'medium', 'high'])],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['nullable', 'url', 'max:2048'],
        ]);

        $validated['reference_images'] = array_values(array_filter($validated['reference_images'] ?? []));

        try {
            $generation = $creator->create($request->user(), $validated);
        } catch (InsufficientCreditsException $e) {
            return back()->withErrors(['prompt' => $e->getMessage()]);
        }

        return redirect()
            ->route('generations.index')
            ->with('success', "Generation queued (#{$generation->id}).");
    }

    public function index(Request $request): Response
    {
        $generations = Generation::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Generation $g) => $g->toHistoryArray());

        $hasInFlight = $generations->contains(
            fn (array $g) => in_array($g['status'], ['pending', 'queued', 'processing'], true)
        );

        return Inertia::render('History', [
            'generations' => $generations,
            'has_in_flight' => $hasInFlight,
            'balance' => $request->user()->credit_balance,
        ]);
    }
}
