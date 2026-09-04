<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    showName?: boolean;
    firstNameOnly?: boolean;
    avatarOnRight?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showName: true,
    firstNameOnly: false,
    avatarOnRight: false,
});

const { getInitials } = useInitials();

const showAvatar = computed(
    () => props.user?.avatar !== '',
);
const displayName = computed(() => {
    if (!props.firstNameOnly) {
        return props.user.name;
    }

    return (
        String(props.user?.first_name ?? '').trim() ||
        props.user?.name?.split(/\s+/)[0] || ''
    );
});
</script>

<template>
    <template v-if="avatarOnRight">
        <div
            v-if="showName"
            class="grid min-w-0 flex-1 text-left text-sm leading-tight"
        >
            <span class="truncate font-medium">{{ displayName }}</span>
            <span
                v-if="showEmail"
                class="truncate text-xs text-muted-foreground"
                >{{ user.email }}</span
            >
        </div>
        <slot name="before-avatar" />
        <Avatar v-if="user"
            class="size-8 overflow-hidden rounded-full ring-2 ring-amber-400/70"
        >
            <AvatarImage
                v-if="showAvatar"
                :src="user.avatar!"
                :alt="user.name"
            />
            <AvatarFallback
                class="rounded-full bg-[var(--church-primary,var(--primary))] text-white"
            >
                {{ getInitials(user.name) }}
            </AvatarFallback>
        </Avatar>
    </template>
    <template v-else>
        <Avatar
            class="size-8 overflow-hidden rounded-full ring-2 ring-amber-400/70"
        >
            <AvatarImage
                v-if="showAvatar"
                :src="user.avatar!"
                :alt="user.name"
            />
            <AvatarFallback
                class="rounded-full bg-[var(--church-primary,var(--primary))] text-white"
            >
                {{ getInitials(user.name) }}
            </AvatarFallback>
        </Avatar>
        <div
            v-if="showName"
            class="grid min-w-0 flex-1 text-left text-sm leading-tight"
        >
            <span class="truncate font-medium">{{ displayName }}</span>
            <span
                v-if="showEmail"
                class="truncate text-xs text-muted-foreground"
                >{{ user.email }}</span
            >
        </div>
    </template>
</template>
