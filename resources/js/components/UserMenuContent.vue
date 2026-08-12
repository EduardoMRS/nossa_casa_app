<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useI18n } from '@/lib/i18n';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
const { t } = useI18n();
const page = usePage<{
    auth?: {
        notifications?: Array<{
            id: string;
            type: string;
            child_name: string;
            classroom_name: string;
            pickup_name: string;
            pickup_phone: string | null;
        }>;
    };
}>();
const notifications = computed(() => page.props.auth?.notifications ?? []);
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <template v-if="notifications.length">
        <DropdownMenuLabel
            class="flex items-center gap-2 text-xs font-black uppercase"
        >
            <Bell class="size-3.5" /> {{ t('notifications.title') }}
        </DropdownMenuLabel>
        <div class="max-h-56 space-y-1 overflow-y-auto px-1 pb-1">
            <div
                v-for="notification in notifications"
                :key="notification.id"
                class="rounded-md bg-amber-50 p-2 text-xs leading-5 text-amber-950"
            >
                {{
                    t('notifications.child_released', {
                        child: notification.child_name,
                        person: notification.pickup_name,
                        classroom: notification.classroom_name,
                    })
                }}
                <span
                    v-if="notification.pickup_phone"
                    class="block font-bold"
                    >{{ notification.pickup_phone }}</span
                >
            </div>
        </div>
        <DropdownMenuSeparator />
    </template>
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('settings.title') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('auth.verify.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
