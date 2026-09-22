<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    Bell,
    BookOpen,
    CircleAlert,
    House,
    LayoutDashboard,
    LogOut,
    Settings,
    Undo2,
} from '@lucide/vue';
import { computed } from 'vue';
import ClassroomPortalController from '@/actions/App/Http/Controllers/ClassroomPortalController';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useI18n } from '@/lib/i18n';
import { dashboard, home, logout } from '@/routes';
import { switchMethod } from '@/routes/church/membership';
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
const { confirm } = useConfirmDialog();
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
const churchContext = computed(
    () =>
        page.props.churchContext as
            | {
                  isForeignChurch?: boolean;
                  church?: { name: string } | null;
                  userChurch?: { name: string; url: string } | null;
              }
            | undefined,
);
const isForeignChurch = computed(
    () => churchContext.value?.isForeignChurch === true,
);
const canAccessDashboard = computed(
    () =>
        (page.props.permissions as { accessDashboard?: boolean } | undefined)
            ?.accessDashboard === true,
);
const isDashboard = computed(() => page.url.startsWith('/dashboard'));
const hasClassroomAccess = computed(
    () =>
        (page.props.classrooms as { hasAccess?: boolean } | undefined)
            ?.hasAccess === true,
);

const transferMembership = async (): Promise<void> => {
    if (
        !(await confirm({
            message: t('membership.confirm', {
                church: churchContext.value?.church?.name ?? '',
            }),
        }))
    ) {
        return;
    }

    router.post(switchMethod(), { confirmed: true });
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <div
        v-if="isForeignChurch"
        class="mx-1 mb-1 rounded-lg border border-sky-200 bg-sky-50 p-2.5 text-xs text-sky-950"
    >
        <p class="flex items-center gap-2 font-black">
            <CircleAlert class="size-4 text-sky-600" />
            {{ t('membership.visiting_title') }}
        </p>
        <p class="mt-1 leading-5 text-sky-800">
            {{
                t('membership.visiting_description', {
                    church: churchContext?.church?.name ?? '',
                    current: churchContext?.userChurch?.name ?? '',
                })
            }}
        </p>
    </div>
    <DropdownMenuGroup v-if="isForeignChurch">
        <DropdownMenuItem
            v-if="churchContext?.userChurch?.url"
            :as-child="true"
        >
            <a
                class="block w-full cursor-pointer"
                :href="churchContext.userChurch.url"
                :data-church-id="churchContext.userChurch.id"
            >
                <Undo2 class="mr-2 size-4" />
                {{ t('membership.return_to_own_church') }}
            </a>
        </DropdownMenuItem>
        <DropdownMenuItem
            class="cursor-pointer text-sky-700 focus:text-sky-800"
            @select.prevent="transferMembership"
        >
            <ArrowRightLeft class="mr-2 size-4" />
            {{ t('membership.transfer') }}
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator v-if="isForeignChurch" />
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
                    t(
                        notification.type === 'child_checked_in'
                            ? 'notifications.child_checked_in'
                            : 'notifications.child_released',
                        {
                            child: notification.child_name,
                            person: notification.pickup_name,
                            classroom: notification.classroom_name,
                        },
                    )
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
        <DropdownMenuItem
            v-if="isDashboard || canAccessDashboard"
            :as-child="true"
        >
            <Link
                class="block w-full cursor-pointer"
                :href="isDashboard ? home() : dashboard()"
                prefetch
            >
                <House v-if="isDashboard" class="mr-2 size-4" />
                <LayoutDashboard v-else class="mr-2 size-4" />
                {{ isDashboard ? t('nav.home') : t('nav.dashboard') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="hasClassroomAccess" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="ClassroomPortalController.index.url()"
                prefetch
            >
                <BookOpen class="mr-2 size-4" />
                {{ t('nav.classrooms') }}
            </Link>
        </DropdownMenuItem>
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
