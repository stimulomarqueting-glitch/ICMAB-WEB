/**
 * Client-side lead tracking utilities:
 *  - trackEvent(): lightweight analytics wrapper (no-op friendly)
 *  - UTM capture: persists utm_* params for the session
 *  - CTA source: remembers which CTA block sent the user to the form
 *
 * No external analytics tool is loaded. When one is added (Plausible, GA4,
 * GTM…), connect it inside trackEvent() — every conversion event already
 * flows through there.
 */

const CTA_KEY = 'lead:cta_source';
const UTM_KEY = 'lead:utm';

export type LeadEventName =
  | 'cta_click'
  | 'form_start'
  | 'form_submit_attempt'
  | 'form_submit_success'
  | 'form_submit_error';

export interface UtmParams {
  source: string;
  medium: string;
  campaign: string;
  content: string;
  term: string;
}

/* sessionStorage can throw (private mode, blocked storage) — never let
   tracking break the page. */
function storageGet(key: string): string | null {
  try {
    return sessionStorage.getItem(key);
  } catch {
    return null;
  }
}

function storageSet(key: string, value: string): void {
  try {
    sessionStorage.setItem(key, value);
  } catch {
    /* ignore */
  }
}

/**
 * Analytics wrapper. Currently: DOM CustomEvent (so GTM or any script can
 * listen for `lead:track`) + console.debug in dev builds.
 *
 * INTEGRATION POINT — when an analytics tool exists, forward here, e.g.:
 *   window.plausible?.(name, { props: data });
 *   window.gtag?.('event', name, data);
 *   window.dataLayer?.push({ event: name, ...data });
 */
export function trackEvent(name: LeadEventName, data: Record<string, unknown> = {}): void {
  window.dispatchEvent(new CustomEvent('lead:track', { detail: { name, data } }));
  if (import.meta.env.DEV) console.debug('[lead:track]', name, data);
}

/** Store utm_* params once per session (first landing wins). */
export function captureUtmParams(): void {
  if (storageGet(UTM_KEY)) return;
  const params = new URLSearchParams(window.location.search);
  const utm: UtmParams = {
    source: params.get('utm_source') ?? '',
    medium: params.get('utm_medium') ?? '',
    campaign: params.get('utm_campaign') ?? '',
    content: params.get('utm_content') ?? '',
    term: params.get('utm_term') ?? '',
  };
  if (Object.values(utm).some((v) => v !== '')) {
    storageSet(UTM_KEY, JSON.stringify(utm));
  }
}

export function getUtm(): UtmParams {
  const empty: UtmParams = { source: '', medium: '', campaign: '', content: '', term: '' };
  const raw = storageGet(UTM_KEY);
  if (!raw) return empty;
  try {
    return { ...empty, ...JSON.parse(raw) };
  } catch {
    return empty;
  }
}

/**
 * Delegated listener: any element with data-cta-source="hero|header|…"
 * records itself as the origin of the visit to the form.
 */
export function setupCtaTracking(): void {
  document.addEventListener('click', (e) => {
    const el = (e.target as Element | null)?.closest<HTMLElement>('[data-cta-source]');
    if (!el) return;
    const source = el.dataset.ctaSource ?? '';
    if (source) {
      storageSet(CTA_KEY, source);
      trackEvent('cta_click', { source });
    }
  });
}

/** 'direct' when the user reached the form without clicking any tracked CTA. */
export function getCtaSource(): string {
  return storageGet(CTA_KEY) ?? 'direct';
}
