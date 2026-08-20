import { usePage } from '@inertiajs/vue3';

type UnitScope = 'headquarters' | 'branch';

type ResolvedTerminology = {
    units?: Partial<
        Record<UnitScope, { key: string; singular: string; plural: string }>
    >;
    roles?: Record<string, { key: string; label: string }>;
};

const unitFallbacks: Record<UnitScope, { singular: string; plural: string }> = {
    headquarters: { singular: 'Church', plural: 'Churches' },
    branch: { singular: 'Branch', plural: 'Branches' },
};

export const useTerminology = () => {
    const page = usePage();

    const terminology = (): ResolvedTerminology =>
        (page.props.terminology as ResolvedTerminology | undefined) ?? {};

    const roleLabel = (role: string): string =>
        terminology().roles?.[role]?.label ?? role.replaceAll('_', ' ');

    const unitLabel = (
        scope: UnitScope,
        form: 'singular' | 'plural' = 'singular',
    ): string =>
        terminology().units?.[scope]?.[form] ?? unitFallbacks[scope][form];

    return { roleLabel, unitLabel };
};
