<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type DayRow = {
    date: string;
    generations: number;
    credit_spend_display: string;
    provider_cost_display: string;
    profit_display: string;
};

type UserRow = {
    user_id: number;
    name: string | null;
    email: string | null;
    generations: number;
    credit_spend_display: string;
    provider_cost_display: string;
    profit_display: string;
};

type UsageCheck = {
    date: string | null;
    matches?: boolean;
    error?: string;
    provider_cost_display?: string;
    local_cost_display?: string;
    provider_generations?: number;
    local_generations?: number;
};

defineProps<{
    start_date: string;
    end_date: string;
    days: DayRow[];
    users: UserRow[];
    totals: {
        credit_spend_display: string;
        provider_cost_display: string;
        profit_display: string;
    };
    usage_check: UsageCheck[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin report', href: '/admin/report' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Admin report" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <Heading
                title="Operator spend report"
                :description="`UTC days ${start_date} → ${end_date}. Provider costs should match Pixelforge GET /v1/usage.`"
            />

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Credit spend</p>
                    <p class="text-2xl font-semibold">{{ totals.credit_spend_display }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Provider cost</p>
                    <p class="text-2xl font-semibold">{{ totals.provider_cost_display }}</p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Profit</p>
                    <p class="text-2xl font-semibold">{{ totals.profit_display }}</p>
                </div>
            </div>

            <section class="space-y-3">
                <h2 class="text-lg font-medium">By day</h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Gens</th>
                                <th class="px-3 py-2">Spend</th>
                                <th class="px-3 py-2">Provider</th>
                                <th class="px-3 py-2">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in days" :key="day.date" class="border-b last:border-0">
                                <td class="px-3 py-2">{{ day.date }}</td>
                                <td class="px-3 py-2">{{ day.generations }}</td>
                                <td class="px-3 py-2">{{ day.credit_spend_display }}</td>
                                <td class="px-3 py-2">{{ day.provider_cost_display }}</td>
                                <td class="px-3 py-2">{{ day.profit_display }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-lg font-medium">By user</h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2">User</th>
                                <th class="px-3 py-2">Gens</th>
                                <th class="px-3 py-2">Spend</th>
                                <th class="px-3 py-2">Provider</th>
                                <th class="px-3 py-2">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.user_id" class="border-b last:border-0">
                                <td class="px-3 py-2">
                                    <div>{{ user.name }}</div>
                                    <div class="text-muted-foreground">{{ user.email }}</div>
                                </td>
                                <td class="px-3 py-2">{{ user.generations }}</td>
                                <td class="px-3 py-2">{{ user.credit_spend_display }}</td>
                                <td class="px-3 py-2">{{ user.provider_cost_display }}</td>
                                <td class="px-3 py-2">{{ user.profit_display }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-lg font-medium">Pixelforge usage reconciliation</h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Provider gens</th>
                                <th class="px-3 py-2">Local gens</th>
                                <th class="px-3 py-2">Provider cost</th>
                                <th class="px-3 py-2">Local cost</th>
                                <th class="px-3 py-2">Match</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in usage_check" :key="row.date ?? index" class="border-b last:border-0">
                                <td class="px-3 py-2">{{ row.date ?? '—' }}</td>
                                <td class="px-3 py-2">{{ row.provider_generations ?? '—' }}</td>
                                <td class="px-3 py-2">{{ row.local_generations ?? '—' }}</td>
                                <td class="px-3 py-2">{{ row.provider_cost_display ?? row.error ?? '—' }}</td>
                                <td class="px-3 py-2">{{ row.local_cost_display ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <span :class="row.matches ? 'text-green-700 dark:text-green-400' : 'text-destructive'">
                                        {{ row.matches ? 'yes' : 'no' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
