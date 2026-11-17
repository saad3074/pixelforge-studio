export type PricingCatalog = {
    rates: Record<string, string>;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
};

export type Estimate = {
    userMin: bigint;
    userExpected: bigint;
    userMax: bigint;
};

const SCALE = 1_000_000_000n;

function fromDecimalString(amount: string): bigint {
    const negative = amount.startsWith('-');
    const raw = amount.replace(/^[+-]/, '');
    const [whole, fraction = '0'] = raw.split('.');
    const frac = (fraction + '000000000').slice(0, 9);
    const value = BigInt(whole) * SCALE + BigInt(frac);
    return negative ? -value : value;
}

function costForTokens(tokens: number, ratePerMillion: string): bigint {
    const rate = fromDecimalString(ratePerMillion);
    return (BigInt(tokens) * rate) / 1_000_000n;
}

function withMarkup(provider: bigint, markupPercent: number): bigint {
    const numerator = provider * BigInt(100 + markupPercent);
    return (numerator + 99n) / 100n;
}

export function estimatePrice(
    prompt: string,
    size: string,
    quality: string,
    referenceCount: number,
    catalog: PricingCatalog,
    markupPercent: number,
): Estimate {
    const chars = [...prompt].length;
    const perChar = catalog.tokens.characters_per_text_token || 4;
    const variance = catalog.tokens.text_token_variance ?? 0.15;

    const textExpected = Math.ceil(chars / perChar);
    const textMin = Math.floor(textExpected * (1 - variance));
    const textMax = Math.ceil(textExpected * (1 + variance));
    const imageTokens = referenceCount * (catalog.tokens.per_reference_image || 765);
    const outputTokens = catalog.tokens.output[quality]?.[size] ?? 0;
    const reasoning = catalog.tokens.reasoning[quality] ?? { min: 0, max: 0 };
    const reasoningExpected = Math.round((reasoning.min + reasoning.max) / 2);

    const provider = (text: number, reasoningTokens: number) =>
        costForTokens(text, catalog.rates.input_text_tokens) +
        costForTokens(imageTokens, catalog.rates.input_image_tokens) +
        costForTokens(outputTokens, catalog.rates.output_tokens) +
        costForTokens(reasoningTokens, catalog.rates.reasoning_tokens);

    const min = provider(textMin, reasoning.min);
    const expected = provider(textExpected, reasoningExpected);
    const max = provider(textMax, reasoning.max);

    return {
        userMin: withMarkup(min, markupPercent),
        userExpected: withMarkup(expected, markupPercent),
        userMax: withMarkup(max, markupPercent),
    };
}

export function formatMoney(nanos: bigint | number, decimals = 4): string {
    const value = typeof nanos === 'number' ? BigInt(nanos) : nanos;
    const negative = value < 0n;
    const abs = negative ? -value : value;
    const whole = abs / SCALE;
    const fraction = (abs % SCALE).toString().padStart(9, '0').slice(0, decimals);
    return `${negative ? '-' : ''}$${whole.toString()}.${fraction}`;
}
