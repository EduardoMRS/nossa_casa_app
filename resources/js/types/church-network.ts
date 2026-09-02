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

export type ChurchNetworkTreeNode = ChurchNetworkOption & {
    children: ChurchNetworkTreeNode[];
};

export type ChurchNetworkStats = {
    direct_branches: number;
    all_branches: number;
    levels: number;
};

export type ChurchNetworkOverviewData = {
    church: ChurchNetworkOption & { community_id: string | null };
    parent: ChurchNetworkOption | null;
    children: ChurchNetworkOption[];
    ancestors: ChurchNetworkOption[];
    tree: ChurchNetworkTreeNode;
    stats: ChurchNetworkStats;
};

export type ChurchNetworkSettingsData = ChurchNetworkOverviewData & {
    availableChurches: ChurchNetworkOption[];
    networks: ChurchNetworkRow[];
    requests: ChurchNetworkRequest[];
};
