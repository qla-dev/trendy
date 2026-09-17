# Ponuda

Both offers share build dependencies but have separate entry points, stylesheets,
source PDF copies, and public folders. Their page content differs only in the Faza II items.

- Regular offer: `/ponuda/`
- Second offer: `/ponuda-2/`

Source files, dependencies, and source PDFs live in `resources/ponuda`.
The regular offer is defined in `src/App.tsx`; the second is defined in
`ponuda-2/src/ponuda2.tsx`.
Vite builds `public/ponuda/index.html` and `public/ponuda-2/index.html` separately,
each with its own assets directory. Neither public folder loads assets from the other.
Both generated directories are ignored by Git; do not edit their files manually.

The browser redeploy endpoint installs this project's dependencies and the
root production build builds both offers. Both URLs load their page directly
without redirects or a `dist` URL.

For local development, install dependencies in this directory and use its
`dev` script. The root `build:ponuda` script builds both production pages.
