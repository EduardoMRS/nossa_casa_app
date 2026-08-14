<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarDays,
    ClipboardList,
    FolderGit2,
    Image,
    LayoutGrid,
    LibraryBig,
    ListChecks,
    Megaphone,
    MessageSquareWarning,
    Settings2,
    Sparkles,
    Users,
    UserRoundCog,
    Heart,
    School,
    PanelsTopLeft,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useI18n } from '@/lib/i18n';
import { home } from '@/routes';
import { edit as brandingEdit } from '@/routes/admin/branding';
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminClassroomsIndex } from '@/routes/admin/classrooms';
import { index as adminEventsIndex } from '@/routes/admin/events';
import { index as adminFormsIndex } from '@/routes/admin/forms';
import { index as adminGalleryModerationIndex } from '@/routes/admin/galleryModeration';
import { index as adminHighlightsIndex } from '@/routes/admin/highlights';
import { index as adminKidsMinistryIndex } from '@/routes/admin/kidsMinistry';
import { index as adminLibraryVerseIndex } from '@/routes/admin/libraryVerse';
import { index as adminLogsMetricsIndex } from '@/routes/admin/logsMetrics';
import { index as adminMultiCongregationIndex } from '@/routes/admin/multiCongregation';
import { index as adminUserManagementIndex } from '@/routes/admin/userManagement';
import { index as adminWallModerationIndex } from '@/routes/admin/wallModeration';
import { index as myPrayersIndex } from '@/routes/myPrayers';
import { index as postsIndex } from '@/routes/posts';
import { ui as apiDocs } from '@/routes/scramble/docs';
import type { NavItem } from '@/types';

const { t } = useI18n();

const page = usePage<{
    auth?: {
        user?: {
            role?: string | { value?: string };
        };
    };
    separateKidsMinistry?: boolean;
}>();

const enabledKidsMinistry = computed(() => {
    return page.props.separateKidsMinistry === true;
});

const role = computed(() => {
    const rawRole = page.props.auth?.user?.role;

    if (typeof rawRole === 'string') {
        return rawRole;
    }

    if (rawRole && typeof rawRole === 'object' && 'value' in rawRole) {
        return rawRole.value ?? '';
    }

    return '';
});

const canManageWorkspace = computed(() =>
    ['admin', 'superadmin', 'system'].includes(role.value),
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.dashboard'),
        href: home(),
        icon: LayoutGrid,
    },
    {
        title: t('admin.prayers.title'),
        href: myPrayersIndex(),
        icon: Heart,
    },
]);

const communicationNavItems = computed<NavItem[]>(() => [
    {
        title: t('posts.index.title'),
        href: postsIndex(),
        icon: Megaphone,
    },
    {
        title: t('navigation.highlights'),
        href: adminHighlightsIndex(),
        icon: Sparkles,
    },
    {
        title: t('admin.media.title'),
        href: adminGalleryModerationIndex(),
        icon: Image,
    },
    {
        title: t('admin.wall.title'),
        href: adminWallModerationIndex(),
        icon: MessageSquareWarning,
    },
    {
        title: t('admin.library.title'),
        href: adminLibraryVerseIndex(),
        icon: LibraryBig,
    },
    {
        title: t('admin.branding.title'),
        href: brandingEdit(),
        icon: Settings2,
    },
]);

const ministriesNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.events'),
        href: adminEventsIndex(),
        icon: CalendarDays,
    },
    {
        title: t('admin.forms.title'),
        href: adminFormsIndex(),
        icon: ClipboardList,
    },
    ...(enabledKidsMinistry.value
        ? [
              {
                  title: t('admin.classrooms.kids_title'),
                  href: adminKidsMinistryIndex(),
                  icon: School,
              },
          ]
        : []),
]);

const administrationNavItems = computed<NavItem[]>(() => [
    {
        title: t('admin.users.title'),
        href: adminUserManagementIndex(),
        icon: UserRoundCog,
    },
    {
        title: t('admin.categories.title'),
        href: adminCategoriesIndex(),
        icon: FolderGit2,
    },
    {
        title: t('admin.multicongregation.title'),
        href: adminMultiCongregationIndex(),
        icon: Users,
    },
    {
        title: t('admin.classrooms.title'),
        href: adminClassroomsIndex(),
        icon: PanelsTopLeft,
    },
    ...(role.value === 'system'
        ? [
              {
                  title: t('admin.logs.title'),
                  href: adminLogsMetricsIndex(),
                  icon: ListChecks,
              },
          ]
        : []),
]);

const footerNavItems = computed<NavItem[]>(() => [
    {
        title: t('navigation.documentation'),
        href: apiDocs(),
        icon: BookOpen,
    },
    {
        title: t('navigation.repository'),
        href: 'https://github.com/EduardoMRS/nossa_casa_app',
        icon: FolderGit2,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" :label="t('navigation.shortcuts')" />

            <template v-if="canManageWorkspace">
                <NavMain
                    :items="communicationNavItems"
                    :label="t('navigation.communication')"
                />
                <NavMain
                    :items="ministriesNavItems"
                    :label="t('navigation.ministries')"
                />
                <NavMain
                    :items="administrationNavItems"
                    :label="t('navigation.administration')"
                />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
