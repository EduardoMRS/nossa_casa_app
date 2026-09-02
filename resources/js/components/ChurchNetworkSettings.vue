<script setup lang="ts">
import { Check, GitBranch, Network, Send, X } from '@lucide/vue';
import axios from 'axios';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/lib/i18n';

type ChurchOption = { id: string; name: string };
type NetworkRow = {
    id: string;
    parent: ChurchOption | null;
    child: ChurchOption | null;
};
type NetworkRequest = {
    id: string;
    direction: 'incoming' | 'outgoing';
    requesting_church: ChurchOption | null;
    parent: ChurchOption | null;
    child: ChurchOption | null;
};
type NetworkSettings = {
    church: ChurchOption & { community_id: string | null };
    parent: ChurchOption | null;
    children: ChurchOption[];
    availableChurches: ChurchOption[];
    networks: NetworkRow[];
    requests: NetworkRequest[];
};

const props = defineProps<{ settings: NetworkSettings }>();
const { t } = useI18n();
const selectedParentId = ref('');
const selectedChildId = ref('');
const movingNetworkId = ref('');
const moveParentId = ref('');
const processing = ref(false);
const error = ref('');
const incomingRequests = computed(() =>
    props.settings.requests.filter((request) => request.direction === 'incoming'),
);
const outgoingRequests = computed(() =>
    props.settings.requests.filter((request) => request.direction === 'outgoing'),
);
const selectedMovingNetwork = computed(() =>
    props.settings.networks.find((network) => network.id === movingNetworkId.value),
);
const moveParentOptions = computed(() => {
    const childId = selectedMovingNetwork.value?.child?.id;

    return [
        props.settings.church,
        ...props.settings.networks
            .map((network) => network.child)
            .filter((church): church is ChurchOption => church !== null),
    ].filter((church) => church.id !== childId);
});

const requestError = (requestError: unknown): string => {
    if (axios.isAxiosError(requestError)) {
        const errors = requestError.response?.data?.errors as
            | Record<string, string[]>
            | undefined;

        return (
            (errors ? Object.values(errors).flat()[0] : undefined) ??
            requestError.response?.data?.message ??
            t('admin.branding.network.errors.generic')
        );
    }

    return t('admin.branding.network.errors.generic');
};

const sendRequest = async (
    parentChurchId: string,
    childChurchId: string,
): Promise<void> => {
    processing.value = true;
    error.value = '';

    try {
        await axios.post('/api/networks', {
            parent_church_id: parentChurchId,
            child_church_id: childChurchId,
        });
        window.location.reload();
    } catch (requestErrorValue) {
        error.value = requestError(requestErrorValue);
    } finally {
        processing.value = false;
    }
};

const requestParent = (): void => {
    if (selectedParentId.value) {
        void sendRequest(selectedParentId.value, props.settings.church.id);
    }
};

const inviteChild = (): void => {
    if (selectedChildId.value) {
        void sendRequest(props.settings.church.id, selectedChildId.value);
    }
};

const respond = async (requestId: string, accept: boolean): Promise<void> => {
    processing.value = true;
    error.value = '';

    try {
        await axios.post(
            `/api/networks/requests/${requestId}/${accept ? 'accept' : 'reject'}`,
        );
        window.location.reload();
    } catch (requestErrorValue) {
        error.value = requestError(requestErrorValue);
    } finally {
        processing.value = false;
    }
};

const moveBranch = async (): Promise<void> => {
    if (!movingNetworkId.value || !moveParentId.value) {
        return;
    }

    processing.value = true;
    error.value = '';

    try {
        await axios.put(`/api/networks/${movingNetworkId.value}`, {
            new_parent_church_id: moveParentId.value,
        });
        window.location.reload();
    } catch (requestErrorValue) {
        error.value = requestError(requestErrorValue);
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <section
        id="settings-network"
        class="space-y-5 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm md:p-6"
    >
        <header class="flex items-start gap-3">
            <span class="rounded-xl bg-primary/10 p-2 text-primary">
                <Network class="size-5" />
            </span>
            <div>
                <h2 class="font-black">
                    {{ t('admin.branding.network.title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('admin.branding.network.description') }}
                </p>
            </div>
        </header>

        <p
            v-if="error"
            class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
        >
            {{ error }}
        </p>

        <div class="grid gap-4 lg:grid-cols-2">
            <article class="space-y-3 rounded-xl border border-border p-4">
                <h3 class="font-bold">
                    {{ t('admin.branding.network.parent.title') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{
                        settings.parent?.name ??
                        t('admin.branding.network.parent.none')
                    }}
                </p>
                <div class="grid gap-2">
                    <Label for="network-parent">{{
                        t('admin.branding.network.parent.select')
                    }}</Label>
                    <select
                        id="network-parent"
                        v-model="selectedParentId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option value="">
                            {{ t('admin.branding.network.select_placeholder') }}
                        </option>
                        <option
                            v-for="church in settings.availableChurches"
                            :key="church.id"
                            :value="church.id"
                        >
                            {{ church.name }}
                        </option>
                    </select>
                    <Button
                        type="button"
                        :disabled="processing || !selectedParentId"
                        @click="requestParent"
                    >
                        <Send class="size-4" />
                        {{ t('admin.branding.network.parent.request') }}
                    </Button>
                </div>
            </article>

            <article class="space-y-3 rounded-xl border border-border p-4">
                <h3 class="font-bold">
                    {{ t('admin.branding.network.children.title') }}
                </h3>
                <div
                    v-if="settings.children.length"
                    class="flex flex-wrap gap-2"
                >
                    <span
                        v-for="church in settings.children"
                        :key="church.id"
                        class="rounded-full bg-muted px-3 py-1 text-xs font-bold"
                    >
                        {{ church.name }}
                    </span>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('admin.branding.network.children.none') }}
                </p>
                <div class="grid gap-2">
                    <Label for="network-child">{{
                        t('admin.branding.network.children.select')
                    }}</Label>
                    <select
                        id="network-child"
                        v-model="selectedChildId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option value="">
                            {{ t('admin.branding.network.select_placeholder') }}
                        </option>
                        <option
                            v-for="church in settings.availableChurches"
                            :key="church.id"
                            :value="church.id"
                        >
                            {{ church.name }}
                        </option>
                    </select>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing || !selectedChildId"
                        @click="inviteChild"
                    >
                        <Send class="size-4" />
                        {{ t('admin.branding.network.children.invite') }}
                    </Button>
                </div>
            </article>
        </div>

        <article
            v-if="incomingRequests.length || outgoingRequests.length"
            class="space-y-3 rounded-xl border border-border p-4"
        >
            <h3 class="font-bold">
                {{ t('admin.branding.network.requests.title') }}
            </h3>
            <div
                v-for="request in incomingRequests"
                :key="request.id"
                class="flex flex-col gap-3 rounded-xl bg-muted/60 p-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm">
                    {{
                        t('admin.branding.network.requests.link', {
                            parent: request.parent?.name ?? '',
                            child: request.child?.name ?? '',
                        })
                    }}
                </p>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :disabled="processing"
                        @click="respond(request.id, true)"
                    >
                        <Check class="size-4" />
                        {{ t('actions.accept') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="processing"
                        @click="respond(request.id, false)"
                    >
                        <X class="size-4" />
                        {{ t('actions.reject') }}
                    </Button>
                </div>
            </div>
            <div
                v-for="request in outgoingRequests"
                :key="request.id"
                class="rounded-xl bg-muted/60 p-3 text-sm text-muted-foreground"
            >
                {{
                    t('admin.branding.network.requests.pending', {
                        parent: request.parent?.name ?? '',
                        child: request.child?.name ?? '',
                    })
                }}
            </div>
        </article>

        <article
            v-if="settings.networks.length"
            class="space-y-3 rounded-xl border border-border p-4"
        >
            <div class="flex items-center gap-2">
                <GitBranch class="size-4 text-primary" />
                <h3 class="font-bold">
                    {{ t('admin.branding.network.reorganize.title') }}
                </h3>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ t('admin.branding.network.reorganize.description') }}
            </p>
            <div class="grid gap-3 md:grid-cols-2">
                <select
                    v-model="movingNetworkId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    @change="moveParentId = ''"
                >
                    <option value="">
                        {{ t('admin.branding.network.reorganize.branch') }}
                    </option>
                    <option
                        v-for="network in settings.networks"
                        :key="network.id"
                        :value="network.id"
                    >
                        {{ network.child?.name }}
                    </option>
                </select>
                <select
                    v-model="moveParentId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                >
                    <option value="">
                        {{ t('admin.branding.network.reorganize.parent') }}
                    </option>
                    <option
                        v-for="church in moveParentOptions"
                        :key="church.id"
                        :value="church.id"
                    >
                        {{ church.name }}
                    </option>
                </select>
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="processing || !movingNetworkId || !moveParentId"
                @click="moveBranch"
            >
                {{ t('admin.branding.network.reorganize.action') }}
            </Button>
        </article>
    </section>
</template>
