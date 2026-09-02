export type ChurchNetworkOption = { id: string; name: string };

export type ChurchNetworkRow = {
    id: string;
    parent: ChurchNetworkOption | null;
    child: ChurchNetworkOption | null;
};

export type ChurchNetworkRequest = {
    id: string;
    direction: 'incoming' | 'outgoing';
    requesting_church: ChurchNetworkOption | null;
    parent: ChurchNetworkOption | null;
    child: ChurchNetworkOption | null;
};

export type ChurchNetworkSettingsData = {
    church: ChurchNetworkOption & { community_id: string | null };
    parent: ChurchNetworkOption | null;
    children: ChurchNetworkOption[];
    availableChurches: ChurchNetworkOption[];
    networks: ChurchNetworkRow[];
    requests: ChurchNetworkRequest[];
};
