<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/pricing';
import type { BreadcrumbItem } from '@/types';

type GenerationRow = {
    id: number;
    status: string;
    prompt: string;
    size: string;
    quality: string;
    estimated_user_expected: number | null;
    estimated_user_min: number | null;
    estimated_user_max: number | null;
    user_cost: number | null;
    image_url: string | null;
    image_expired: boolean;
    error_message: string | null;
    created_at: string | null;
};

const props = defineProps<{
    generations: GenerationRow[];
    has_in_flight: boolean;
    balance: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'History', href: '/generations' }];

let timer: ReturnType<typeof setInterval> | null = null;

function startPolling() {
    stopPolling();
    if (!props.has_in_flight) {
        return;
    }
    timer = setInterval(() => {
        router.reload({ only: ['generations', 'has_in_flight', 'balance'], preserveScroll: true });
    }, 2500);
}

function stopPolling() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

watch(
    () => props.has_in_flight,
    () => startPolling(),
);

onMounted(startPolling);
onUnmounted(stopPolling);

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'succeeded') return 'default';
    if (status === 'failed') return 'destructive';
    return 'secondary';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="History" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <Heading
                title="Generation history"
                description="Live status, estimated vs actual cost, and the image when ready."
            />

            <p class="text-sm text-muted-foreground">
                Balance: <span class="font-medium text-foreground">{{ formatMoney(balance) }}</span>
                <span v-if="has_in_flight"> · Updating live…</span>
            </p>

            <div v-if="generations.length === 0" class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                No generations yet.
                <a href="/generate" class="ml-1 underline">Create one</a>
            </div>

            <div class="grid gap-4">
                <article
                    v-for="generation in generations"
                    :key="generation.id"
                    class="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-[180px_1fr] dark:border-sidebar-border"
                >
                    <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-muted">
                        <img
                            v-if="generation.image_url"
                            :src="generation.image_url"
                            :alt="generation.prompt"
                            class="h-full w-full object-cover"
                        />
                        <span v-else-if="generation.image_expired" class="px-3 text-center text-xs text-muted-foreground">
                            Image URL expired
                        </span>
                        <span v-else class="px-3 text-center text-xs text-muted-foreground">
                            {{ generation.status === 'failed' ? 'No image' : 'Waiting…' }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :variant="statusVariant(generation.status)">{{ generation.status }}</Badge>
                            <span class="text-xs text-muted-foreground">#{{ generation.id }} · {{ generation.size }} · {{ generation.quality }}</span>
                        </div>
                        <p class="text-sm leading-relaxed">{{ generation.prompt }}</p>
                        <div class="grid gap-1 text-sm text-muted-foreground sm:grid-cols-2">
                            <p>
                                Estimate:
                                <span class="text-foreground">
                                    {{
                                        generation.estimated_user_expected != null
                                            ? formatMoney(generation.estimated_user_expected)
                                            : '—'
                                    }}
                                </span>
                                <span v-if="generation.estimated_user_min != null && generation.estimated_user_max != null">
                                    ({{ formatMoney(generation.estimated_user_min) }}–{{ formatMoney(generation.estimated_user_max) }})
                                </span>
                            </p>
                            <p>
                                Actual:
                                <span class="text-foreground">
                                    {{
                                        generation.user_cost != null
                                            ? formatMoney(generation.user_cost)
                                            : generation.status === 'failed'
                                              ? '$0.0000'
                                              : 'pending'
                                    }}
                                </span>
                            </p>
                        </div>
                        <p v-if="generation.error_message" class="text-sm text-destructive">
                            {{ generation.error_message }}
                        </p>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
