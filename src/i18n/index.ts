/**
 * Locale helpers. English lives at the root, Spanish under /es — same slugs
 * in both languages. Content files keep locale-neutral hrefs ('/product');
 * every component localises them at render time with `localePath`, so no
 * translated copy has to remember the prefix.
 */

export const locales = ['en', 'es'] as const;
export type Locale = (typeof locales)[number];
export const defaultLocale: Locale = 'en';

export const localeMeta: Record<
  Locale,
  { lang: string; ogLocale: string; label: string; name: string; sitemap: string }
> = {
  en: { lang: 'en', ogLocale: 'en_GB', label: 'EN', name: 'English', sitemap: 'en-GB' },
  es: { lang: 'es', ogLocale: 'es_ES', label: 'ES', name: 'Español', sitemap: 'es-ES' },
};

const PREFIX = /^\/es(?=\/|$)/;

/** Locale of a pathname: '/es/product' → 'es', '/product' → 'en'. */
export function localeFromPath(pathname: string): Locale {
  return PREFIX.test(pathname) ? 'es' : 'en';
}

export function getLocale(url: URL): Locale {
  return localeFromPath(url.pathname);
}

/** Pathname without any locale prefix ('/es/product' → '/product'). */
export function stripLocale(pathname: string): string {
  return pathname.replace(PREFIX, '') || '/';
}

/**
 * Prefix an internal href for a locale. External links, mailto:, bare
 * anchors and already-prefixed paths pass through untouched.
 */
export function localePath(locale: Locale, href: string): string {
  if (locale === defaultLocale || !href.startsWith('/') || PREFIX.test(href)) return href;
  return href === '/' ? `/${locale}/` : `/${locale}${href}`;
}

/** The same page in another locale, keeping the query string. */
export function switchLocale(url: URL, target: Locale): string {
  return localePath(target, stripLocale(url.pathname)) + url.search;
}

/** getStaticPaths for the [...lang] routes: root (EN) and /es. */
export function localeStaticPaths() {
  return [{ params: { lang: undefined } }, { params: { lang: 'es' } }];
}
