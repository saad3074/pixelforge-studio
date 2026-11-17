<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Coins, History, ImagePlus, LayoutGrid, Shield } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { formatMoney } from '@/lib/pricing';
import type { NavItem } from '@/types';

const page = usePage();
const isAdmin = computed(() => Boolean(page.props.auth.user?.is_admin));
const balance = computed(() => Number(page.props.auth.user?.credit_balance ?? 0));

const mainNavItems = computed((): NavItem[] => {
    const items: NavItem[] = [
        { title: 'Generate', href: '/generate', icon: ImagePlus },
        { title: 'History', href: '/generations', icon: History },
        { title: 'Credits', href: '/credits', icon: Coins },
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    ];

    if (isAdmin.value) {
        items.push({ title: 'Admin report', href: '/admin/report', icon: Shield });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/generate">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <p v-if="page.props.auth.user" class="px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                Balance {{ formatMoney(balance) }}
            </p>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
