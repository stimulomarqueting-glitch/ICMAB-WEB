import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import { SITE_URL } from './src/config/site.mjs';

// https://astro.build/config
export default defineConfig({
  // TODO(client): replace with the final production domain once it is decided.
  site: SITE_URL,
  trailingSlash: 'ignore',
  integrations: [sitemap()],
  // First deliverable ships EN only. The Spanish content files are kept in
  // src/content/es.ts; to re-enable ES, restore src/pages/es/index.astro,
  // the i18n block and the hreflang tags (see git history).
  redirects: {
    '/es': '/',
  },
});
