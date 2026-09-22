import { computed, inject, ref } from 'vue';
import type { App, ComputedRef, InjectionKey, Ref } from 'vue';
import en from '@/locales/en.json';
import pt from '@/locales/pt.json';

interface Catalog {
    [key: string]: string | Catalog;
}

interface I18nContext {
    locale: ComputedRef<SupportedLocale>;
    setLocale: (locale: SupportedLocale) => void;
    t: (key: string, replacements?: Record<string, string | number>) => string;
}

export type SupportedLocale = 'en' | 'pt';

export const supportedLocales: readonly SupportedLocale[] = ['pt', 'en'];

const localeStorageKey = 'ncapp.locale';
const localeCookieKey = 'ncapp_locale';
const i18nKey: InjectionKey<I18nContext> = Symbol('ncapp-i18n');

const catalogs: Record<SupportedLocale, Catalog> = {
    en: en as unknown as Catalog,
    pt: pt as unknown as Catalog,
};

const normalizeLocale = (locale: unknown): SupportedLocale | null => {
    if (typeof locale !== 'string') {
        return null;
    }

    const normalized = locale.toLowerCase().replace('_', '-').split('-')[0];

    return normalized in catalogs ? (normalized as SupportedLocale) : null;
};

const detectBrowserLocale = (): SupportedLocale => {
    if (typeof document === 'undefined') {
        return 'en';
    }

    return (
        normalizeLocale(document.documentElement.lang) ??
        normalizeLocale(localStorage.getItem(localeStorageKey)) ??
        normalizeLocale(navigator.language) ??
        'en'
    );
};

const persistLocale = (locale: SupportedLocale): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.lang = locale;
    localStorage.setItem(localeStorageKey, locale);
    document.cookie = `${localeCookieKey}=${locale}; Path=/; SameSite=Lax; Max-Age=31536000`;
};

const readPath = (source: Catalog, path: string): string | undefined => {
    const segments = path.split('.');
    let current: string | Catalog | undefined = source;

    for (const segment of segments) {
        if (!current || typeof current === 'string') {
            return undefined;
        }

        current = current[segment];
    }

    return typeof current === 'string' ? current : undefined;
};

const interpolate = (
    template: string,
    replacements?: Record<string, string | number>,
): string => {
    if (!replacements) {
        return template;
    }

    return template.replace(/\{(.*?)\}/g, (_, key: string) => {
        const value = replacements[key.trim()];

        return value === undefined ? `{${key}}` : String(value);
    });
};

const createI18nContext = (initialLocale: SupportedLocale): I18nContext => {
    const activeLocale: Ref<SupportedLocale> = ref(initialLocale);
    const locale = computed(() => activeLocale.value);

    const t = (
        key: string,
        replacements?: Record<string, string | number>,
    ): string => {
        const localCatalog = catalogs[activeLocale.value];
        const fallbackCatalog = catalogs.en;

        const localValue = readPath(localCatalog, key);
        const fallbackValue = readPath(fallbackCatalog, key);
        const result = localValue ?? fallbackValue ?? key;

        return interpolate(result, replacements);
    };

    const setLocale = (nextLocale: SupportedLocale): void => {
        if (!(nextLocale in catalogs)) {
            return;
        }

        activeLocale.value = nextLocale;
        persistLocale(nextLocale);
    };

    return {
        locale,
        setLocale,
        t,
    };
};

const fallbackContext = createI18nContext(detectBrowserLocale());

export const installI18n = (app: App, locale: unknown): void => {
    const initialLocale = normalizeLocale(locale) ?? detectBrowserLocale();

    app.provide(i18nKey, createI18nContext(initialLocale));
    persistLocale(initialLocale);
};

export const useI18n = (): I18nContext => inject(i18nKey, fallbackContext);

export const localizedPath = (nextLocale: SupportedLocale): string => {
    if (typeof window === 'undefined') {
        return `/${nextLocale}`;
    }

    const url = new URL(window.location.href);
    const segments = url.pathname.split('/').filter(Boolean);

    if (supportedLocales.includes(segments[0] as SupportedLocale)) {
        segments[0] = nextLocale;
    } else {
        segments.unshift(nextLocale);
    }

    url.pathname = `/${segments.join('/')}`;

    return `${url.pathname}${url.search}${url.hash}`;
};
