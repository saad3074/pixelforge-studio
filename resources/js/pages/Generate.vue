<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { estimatePrice, formatMoney, type PricingCatalog } from '@/lib/pricing';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    pricing: PricingCatalog & { markup_percent: number };
    balance: number;
    sizes: string[];
    qualities: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Generate', href: '/generate' }];

const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [''] as string[],
});

const catalog = computed(
    (): PricingCatalog => ({
        rates: props.pricing.rates,
        tokens: props.pricing.tokens,
    }),
);

const referenceCount = computed(
    () => form.reference_images.filter((url) => url.trim().length > 0).length,
);

const estimate = computed(() =>
    estimatePrice(
        form.prompt,
        form.size,
        form.quality,
        referenceCount.value,
        catalog.value,
        props.pricing.markup_percent,
    ),
);

const hasPrompt = computed(() => form.prompt.trim().length > 0);
const canAfford = computed(() => {
    try {
        return BigInt(props.balance ?? 0) >= estimate.value.userMax;
    } catch {
        return false;
    }
});
const submitDisabled = computed(
    () => form.processing || !hasPrompt.value || !canAfford.value,
);

function addReference() {
    if (form.reference_images.length < 4) {
        form.reference_images.push('');
    }
}

function removeReference(index: number) {
    form.reference_images.splice(index, 1);
    if (form.reference_images.length === 0) {
        form.reference_images.push('');
    }
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            reference_images: data.reference_images.filter((url) => url.trim() !== ''),
        }))
        .post('/generations');
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Generate" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <Heading
                title="Generate an image"
                description="Write a prompt, pick size and quality, and see the price before you submit."
            />

            <div class="rounded-xl border border-sidebar-border/70 bg-background p-4 md:p-6 dark:border-sidebar-border">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-muted-foreground">
                        Balance:
                        <span class="font-medium text-foreground">{{ formatMoney(balance) }}</span>
                    </span>
                    <span class="text-muted-foreground">
                        Markup: {{ pricing.markup_percent }}% over Pixelforge
                    </span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="prompt">Prompt</Label>
                        <textarea
                            id="prompt"
                            v-model="form.prompt"
                            rows="4"
                            maxlength="4000"
                            class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            placeholder="A lighthouse on a cliff at dawn, oil painting"
                            required
                        />
                        <InputError :message="form.errors.prompt" />
                        <p class="text-xs text-muted-foreground">
                            Tip: include [succeed], [fail], [duplicate], [blocked], [slow] to test behaviours.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="size">Size</Label>
                            <select
                                id="size"
                                v-model="form.size"
                                class="border-input flex h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option v-for="size in sizes" :key="size" :value="size">{{ size }}</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label for="quality">Quality</Label>
                            <select
                                id="quality"
                                v-model="form.quality"
                                class="border-input flex h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs"
                            >
                                <option v-for="quality in qualities" :key="quality" :value="quality">
                                    {{ quality }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>Reference image URLs (optional, max 4)</Label>
                            <Button type="button" variant="outline" size="sm" :disabled="form.reference_images.length >= 4" @click="addReference">
                                Add URL
                            </Button>
                        </div>
                        <div v-for="(_, index) in form.reference_images" :key="index" class="flex gap-2">
                            <Input v-model="form.reference_images[index]" type="url" placeholder="https://example.com/style.png" />
                            <Button type="button" variant="ghost" @click="removeReference(index)">Remove</Button>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-4">
                        <p class="text-sm font-medium">Estimated price</p>
                        <p class="mt-1 text-2xl font-semibold tracking-tight">
                            {{ formatMoney(estimate.userExpected) }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Range {{ formatMoney(estimate.userMin) }} – {{ formatMoney(estimate.userMax) }}
                            (final cost uses actual tokens; failed generations are free)
                        </p>
                        <p v-if="!canAfford" class="mt-2 text-sm text-destructive">
                            Not enough credits for the maximum estimate. Buy credits first.
                        </p>
                        <p v-else-if="!hasPrompt" class="mt-2 text-sm text-muted-foreground">
                            Enter a prompt to enable Generate.
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                        :disabled="submitDisabled"
                    >
                        {{ form.processing ? 'Submitting…' : 'Generate' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
