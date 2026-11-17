<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/pricing';
import type { BreadcrumbItem } from '@/types';

type Tx = {
    id: number;
    type: string;
    amount: number;
    amount_display: string;
    balance_after_display: string;
    generation_id: number | null;
    created_at: string | null;
};

defineProps<{
    balance: number;
    balance_display: string;
    pack_amount: number;
    pack_display: string;
    transactions: Tx[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Credits', href: '/credits' }];

const form = useForm({
    idempotency_key: crypto.randomUUID(),
});

function buy() {
    form.post('/credits/purchase', {
        onSuccess: () => {
            form.idempotency_key = crypto.randomUUID();
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Credits" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <Heading title="Credits" description="Credits are US dollars. Buy a fake pack — no real payment." />

            <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Current balance</p>
                <p class="mt-1 text-3xl font-semibold">{{ balance_display }}</p>
                <Button class="mt-4" :disabled="form.processing" @click="buy">
                    Buy {{ pack_display }} credits
                </Button>
            </div>

            <div class="space-y-3">
                <h2 class="text-lg font-medium">Recent transactions</h2>
                <div v-if="transactions.length === 0" class="text-sm text-muted-foreground">No transactions yet.</div>
                <div
                    v-for="tx in transactions"
                    :key="tx.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-3 text-sm"
                >
                    <div>
                        <p class="font-medium capitalize">{{ tx.type }}</p>
                        <p class="text-muted-foreground">
                            {{ tx.created_at }}
                            <span v-if="tx.generation_id"> · generation #{{ tx.generation_id }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p>{{ tx.amount_display }}</p>
                        <p class="text-muted-foreground">Balance {{ tx.balance_after_display }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
