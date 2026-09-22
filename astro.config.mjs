import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import { SITE_URL } from './src/config/site.mjs';

// https://astro.build/config
export default defineConfig({
  // TODO(client): replace with the final production domain once it is decided.
  site: SITE_URL,
  trailingSlash: 'ignore',
  integrations: [
    // English at the root, Spanish under /es — the sitemap carries the
    // hreflang pairs (locale detection is by path prefix).
    sitemap({
      i18n: {
        defaultLocale: 'en',
        locales: { en: 'en-GB', es: 'es-ES' },
      },
    }),
  ],
});
