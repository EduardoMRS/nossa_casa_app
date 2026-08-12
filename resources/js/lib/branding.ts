interface BrandingTheme {
    primary_color?: unknown;
    secondary_color?: unknown;
    accent_color?: unknown;
    surface_color?: unknown;
    font_family?: unknown;
}

const themeValue = (value: unknown, fallback: string): string =>
    typeof value === 'string' && value.trim() ? value.trim() : fallback;

export const applyBranding = (branding: unknown): void => {
    if (
        typeof document === 'undefined' ||
        !branding ||
        typeof branding !== 'object'
    ) {
        return;
    }

    const theme = branding as BrandingTheme;
    const root = document.documentElement.style;
    const fontFamily = themeValue(theme.font_family, 'Manrope, ui-sans-serif');

    root.setProperty(
        '--church-primary',
        themeValue(theme.primary_color, '#342f87'),
    );
    root.setProperty(
        '--church-secondary',
        themeValue(theme.secondary_color, '#5f7d95'),
    );
    root.setProperty(
        '--church-accent',
        themeValue(theme.accent_color, '#c88b4a'),
    );
    root.setProperty(
        '--church-surface',
        themeValue(theme.surface_color, '#f8fafc'),
    );
    root.setProperty('--church-font', fontFamily);
    root.setProperty('--font-sans', fontFamily);
};
