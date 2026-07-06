import { brand } from './brand';

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * LEAD CAPTURE CONFIG — single switch to take lead capture live.
 *
 * The waitlist form works in two clearly separated modes:
 *
 *   mode: 'demo'        → validates locally, logs the payload to the console,
 *                         shows the success state. NOTHING is sent anywhere.
 *   mode: 'production'  → POSTs the JSON payload (see README "Lead capture
 *                         setup" for the exact shape) to `formEndpoint`.
 *
 * To go live, Stimulo only needs to edit THIS file:
 *   1. set mode: 'production'
 *   2. set formEndpoint to a real URL
 *
 * If mode is 'production' but formEndpoint is empty, the site does NOT break:
 * a clear warning is printed (build console + browser console) and the form
 * falls back to demo behaviour.
 *
 * ── Endpoint examples (any service that accepts a JSON POST) ────────────────
 *   Formspree:      'https://formspree.io/f/XXXXXXXX'
 *   Make/Zapier:    'https://hook.eu1.make.com/XXXXXXXX'  (webhook → Sheets,
 *                   Airtable, HubSpot, email…)
 *   Airtable proxy: your own serverless function wrapping the Airtable API
 *   HubSpot:        serverless function wrapping the HubSpot Forms API
 *                   (HubSpot's native endpoint expects its own field format —
 *                   map the payload fields in the function)
 *   Custom:         'https://api.yourdomain.com/leads'
 *   Netlify Forms:  needs form-encoded submissions instead of JSON — see the
 *                   note in README "Lead capture setup" before choosing it.
 * ─────────────────────────────────────────────────────────────────────────────
 */

export type LeadCaptureMode = 'demo' | 'production';

export interface LeadCaptureConfig {
  /** 'demo' until a real endpoint is configured. */
  mode: LeadCaptureMode;
  /** URL that receives the lead as a JSON POST. TODO(client): set to go live. */
  formEndpoint: string;
  /** HTTP method used for submission. */
  submissionMethod: 'POST';
  /** Privacy policy URL. TODO(client/legal): point to the real page. */
  privacyUrl: string;
  /** Shown in the error message as a direct-contact fallback. */
  contactEmail: string;
  /**
   * Optional "lead magnet" download block in the waitlist section.
   * Keep disabled until a validated PDF exists.
   * TODO(client): enable + set url when the one-pager is approved.
   */
  leadMagnet: {
    enabled: boolean;
    url: string; // e.g. '/downloads/sublimat-onepager.pdf'
  };
}

export const leadCapture: LeadCaptureConfig = {
  mode: 'demo',
  formEndpoint: '',
  submissionMethod: 'POST',
  privacyUrl: '#', // TODO(client/legal): real privacy policy page
  contactEmail: brand.contactEmail,
  leadMagnet: {
    enabled: false,
    url: '',
  },
};

/** True when the form can actually send data somewhere. */
export const isLive = leadCapture.mode === 'production' && leadCapture.formEndpoint !== '';

/** Misconfiguration: production requested but no endpoint set. */
export const isMisconfigured = leadCapture.mode === 'production' && leadCapture.formEndpoint === '';
