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
    Palette,
    Sparkles,
    Users,
    UserRoundCog,
    Heart,
    HandHelping,
    School,
    PanelsTopLeft,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { dashboard } from '@/routes';
import { edit as brandingEdit } from '@/routes/admin/branding';
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminClassroomsIndex } from '@/routes/admin/classrooms';
import { index as adminFormsIndex } from '@/routes/admin/forms';
import { index as adminGalleryModerationIndex } from '@/routes/admin/galleryModeration';
import { index as adminHighlightsIndex } from '@/routes/admin/highlights';
import { index as adminKidsMinistryIndex } from '@/routes/admin/kidsMinistry';
import { index as adminLibraryVerseIndex } from '@/routes/admin/libraryVerse';
import { index as adminLogsMetricsIndex } from '@/routes/admin/logsMetrics';
import { index as adminMultiCongregationIndex } from '@/routes/admin/multiCongregation';
import { index as adminMyPrayersIndex } from '@/routes/admin/myPrayers';
import { index as adminPrayerRequestsIndex } from '@/routes/admin/prayerRequests';
import { index as adminUserManagementIndex } from '@/routes/admin/userManagement';
import { index as adminWallModerationIndex } from '@/routes/admin/wallModeration';
import { index as eventsIndex } from '@/routes/events';
import { index as postsIndex } from '@/routes/posts';
import type { NavItem } from '@/types';

const page = usePage<{
    auth?: {
        user?: {
            role?: string | { value?: string };
        };
    };
    classrooms?: {
        hasKids?: boolean;
    };
}>();

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

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const communicationNavItems: NavItem[] = [
    {
        title: 'Postagens',
        href: postsIndex(),
        icon: Megaphone,
    },
    {
        title: 'Destaques',
        href: adminHighlightsIndex(),
        icon: Sparkles,
    },
    {
        title: 'Moderar Galeria',
        href: adminGalleryModerationIndex(),
        icon: Image,
    },
    {
        title: 'Moderar Mural',
        href: adminWallModerationIndex(),
        icon: MessageSquareWarning,
    },
    {
        title: 'Biblioteca & Versiculo',
        href: adminLibraryVerseIndex(),
        icon: LibraryBig,
    },
    {
        title: 'Identidade Visual',
        href: brandingEdit(),
        icon: Palette,
    },
];

const ministriesNavItems = computed<NavItem[]>(() => [
    {
        title: 'Eventos',
        href: eventsIndex(),
        icon: CalendarDays,
    },
    {
        title: 'Formularios',
        href: adminFormsIndex(),
        icon: ClipboardList,
    },
    {
        title: 'Pedidos de Intercessao',
        href: adminPrayerRequestsIndex(),
        icon: HandHelping,
    },
    {
        title: 'Ministerio Kids',
        href: adminKidsMinistryIndex(),
        icon: School,
    },
    {
        title: 'Minhas Oracoes',
        href: adminMyPrayersIndex(),
        icon: Heart,
    },
]);

const administrationNavItems: NavItem[] = [
    {
        title: 'Gestao de Usuarios',
        href: adminUserManagementIndex(),
        icon: UserRoundCog,
    },
    {
        title: 'Categorias',
        href: adminCategoriesIndex(),
        icon: FolderGit2,
    },
    {
        title: 'Multicongregacoes',
        href: adminMultiCongregationIndex(),
        icon: Users,
    },
    {
        title: 'Salas de Aula',
        href: adminClassroomsIndex(),
        icon: PanelsTopLeft,
    },
    {
        title: 'Logs & Metricas',
        href: adminLogsMetricsIndex(),
        icon: ListChecks,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Documentacao',
        href: 'https://laravel.com/docs',
        icon: BookOpen,
    },
    {
        title: 'Repositorio',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Atalhos" />

            <template v-if="canManageWorkspace">
                <NavMain
                    :items="communicationNavItems"
                    label="Comunicacao & Conteudo"
                />
                <NavMain
                    :items="ministriesNavItems"
                    label="Ministerios & Membros"
                />
                <NavMain
                    :items="administrationNavItems"
                    label="Administracao & Configuracao"
                />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
