import type { SiteContent } from './types';
import { en } from './en';
import { es } from './es';

export type Locale = 'en' | 'es';

export const locales: Locale[] = ['en', 'es'];
export const defaultLocale: Locale = 'en';

const contentByLocale: Record<Locale, SiteContent> = { en, es };

export function getContent(locale: Locale): SiteContent {
  return contentByLocale[locale] ?? contentByLocale[defaultLocale];
}

/** Path prefix for a locale ('' for the default locale, '/es' for Spanish). */
export function localePath(locale: Locale): string {
  return locale === defaultLocale ? '' : `/${locale}`;
}
