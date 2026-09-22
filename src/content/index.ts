/**
 * Content entry point: `getContent(locale)` returns the whole copy set for
 * one language. The English files define the shapes; the Spanish files are
 * typed against them, so a missing translation fails the build.
 */
import type { Locale } from '../i18n';
import * as en from './en';
import * as es from './es';

export type Content = typeof en;

export function getContent(locale: Locale): Content {
  return locale === 'es' ? es : en;
}
