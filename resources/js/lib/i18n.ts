import { computed, ref } from 'vue';
import en from '@/locales/en.json';
import pt from '@/locales/pt.json';

interface Catalog {
    [key: string]: string | Catalog;
}
export type SupportedLocale = 'en' | 'pt';

const localeStorageKey = 'ncapp.locale';
const localeCookieKey = 'ncapp_locale';

const catalogs: Record<SupportedLocale, Catalog> = {
    en: en as Catalog,
    pt: pt as Catalog,
};

const detectLocale = (): SupportedLocale => {
    if (typeof document === 'undefined') {
        return 'en';
    }

    const persisted = localStorage.getItem(
        localeStorageKey,
    ) as SupportedLocale | null;

    if (persisted && persisted in catalogs) {
        return persisted;
    }

    const htmlLang = document.documentElement.lang
        ?.toLowerCase()
        .split('-')[0] as SupportedLocale | undefined;

    return htmlLang && htmlLang in catalogs ? htmlLang : 'en';
};

const activeLocale = ref<SupportedLocale>(detectLocale());

if (typeof document !== 'undefined') {
    document.cookie = `${localeCookieKey}=${activeLocale.value}; Path=/; SameSite=Lax; Max-Age=31536000`;
}

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

export const setLocale = (locale: SupportedLocale): void => {
    if (locale in catalogs) {
        activeLocale.value = locale;

        if (typeof document !== 'undefined') {
            document.documentElement.lang = locale;
            localStorage.setItem(localeStorageKey, locale);
            document.cookie = `${localeCookieKey}=${locale}; Path=/; SameSite=Lax; Max-Age=31536000`;
        }
    }
};

export const useI18n = () => {
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

    return {
        locale,
        setLocale,
        t,
    };
};
