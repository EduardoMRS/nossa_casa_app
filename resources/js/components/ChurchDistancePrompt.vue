<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Church, MapPin, Navigation } from '@lucide/vue';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppModal from '@/components/AppModal.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/lib/i18n';

type ChurchLink = {
    id: string;
    name: string;
    url: string;
};
type NearbyChurch = ChurchLink & {
    distance_km: number;
};
type ProximityResponse = {
    should_prompt: boolean;
    distance_km?: number | null;
    own_church?: ChurchLink;
    source?: 'network' | 'community';
    alternatives: NearbyChurch[];
};

const page = usePage<{
    auth?: { user?: { id?: string } | null };
    churchContext?: {
        userChurch?: { id: string; name: string; url: string } | null;
    };
}>();
const { t } = useI18n();
const open = ref(false);
const showAlternatives = ref(false);
const result = ref<ProximityResponse | null>(null);
const userChurch = computed(() => page.props.churchContext?.userChurch);
const eligiblePage = computed(() =>
    ['Portal/Index', 'Home'].includes(page.component),
);

const dismissKey = computed(
    () => `ncapp:church-distance-dismissed:${userChurch.value?.id ?? 'none'}`,
);

const closeForSession = (): void => {
    window.sessionStorage.setItem(dismissKey.value, '1');
    open.value = false;
};

const continueToOwnChurch = (): void => {
    const url = result.value?.own_church?.url ?? userChurch.value?.url;

    closeForSession();

    if (url) {
        const target = new URL(url, window.location.origin);

        if (target.host !== window.location.host) {
            window.location.assign(target.toString());
        }
    }
};

const openChurch = (church: NearbyChurch): void => {
    closeForSession();
    window.location.assign(church.url);
};

const checkLocation = async (latitude: number, longitude: number): Promise<void> => {
    if (
        !page.props.auth?.user ||
        !userChurch.value ||
        !eligiblePage.value ||
        window.sessionStorage.getItem(dismissKey.value)
    ) {
        return;
    }

    try {
        const response = await axios.get<ProximityResponse>(
            '/api/church-proximity',
            { params: { latitude, longitude } },
        );

        result.value = response.data;
        open.value = response.data.should_prompt;
    } catch {
        // Proximity is optional and must not block navigation.
    }
};

const handleLocationUpdated = (event: Event): void => {
    const location = (event as CustomEvent<{ latitude: number; longitude: number }>)
        .detail;

    if (location) {
        void checkLocation(location.latitude, location.longitude);
    }
};

onMounted(() => {
    window.addEventListener('ncapp:location-updated', handleLocationUpdated);
    const storedLocation = window.localStorage.getItem('ncapp_portal_location');

    if (!storedLocation) {
        return;
    }

    try {
        const location = JSON.parse(storedLocation) as {
            latitude?: number;
            longitude?: number;
        };

        if (
            typeof location.latitude !== 'number' ||
            typeof location.longitude !== 'number' ||
            !Number.isFinite(location.latitude) ||
            !Number.isFinite(location.longitude)
        ) {
            return;
        }

        void checkLocation(location.latitude, location.longitude);
    } catch {
        // Invalid legacy location data is ignored.
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('ncapp:location-updated', handleLocationUpdated);
});
</script>

<template>
    <AppModal
        v-if="result"
        v-model:open="open"
        :title="t('church_proximity.title')"
        :description="
            t('church_proximity.description', {
                church: userChurch?.name ?? '',
                distance: result.distance_km ?? 0,
            })
        "
        size="md"
        @update:open="(value) => !value && closeForSession()"
    >
        <div
            v-if="showAlternatives"
            class="grid max-h-72 gap-2 overflow-y-auto"
        >
            <a
                v-for="church in result.alternatives"
                :key="church.id"
                :href="church.url"
                class="flex items-center justify-between gap-3 rounded-xl border border-border p-3 transition hover:bg-muted"
                @click.prevent="openChurch(church)"
            >
                <span class="flex min-w-0 items-center gap-3">
                    <span class="rounded-lg bg-primary/10 p-2 text-primary">
                        <Church class="size-4" />
                    </span>
                    <strong class="truncate text-sm">{{ church.name }}</strong>
                </span>
                <span
                    class="shrink-0 text-xs font-bold text-muted-foreground"
                >
                    {{ t('church_proximity.distance', { distance: church.distance_km }) }}
                </span>
            </a>
            <p
                v-if="!result.alternatives.length"
                class="rounded-xl bg-muted p-4 text-sm text-muted-foreground"
            >
                {{ t('church_proximity.no_alternatives') }}
            </p>
        </div>

        <template #footer>
            <Button
                type="button"
                variant="outline"
                @click="continueToOwnChurch"
            >
                <Navigation class="size-4" />
                {{ t('church_proximity.continue_own') }}
            </Button>
            <Button
                v-if="!showAlternatives"
                type="button"
                :disabled="!result.alternatives.length"
                @click="showAlternatives = true"
            >
                <MapPin class="size-4" />
                {{ t('church_proximity.view_nearby') }}
            </Button>
        </template>
    </AppModal>
</template>
