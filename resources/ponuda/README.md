# Ponuda

Both offers share build dependencies but have separate entry points, stylesheets
and public folders.

- Main offer (Faze II, III i IV): `/ponuda/`
- SaraAI offer: `/ponuda-sara-ai/`

Source files, dependencies, and source PDFs live in `resources/ponuda`.
The main offer is defined in `src/App.tsx`, with its PDFs in `assets/`.
The SaraAI offer is defined in `ponuda-sara-ai/src/ponudaSaraAi.tsx`.
Vite builds `public/ponuda/index.html` and `public/ponuda-sara-ai/index.html`
separately, each with its own assets directory. Both generated directories are
ignored by Git; do not edit their files manually.

The former `/ponuda-2/` offer now lives at `/ponuda/`; the previous first offer
was retired. A ponuda redeploy replaces the old `public/ponuda-2/` build with a
redirect to `/ponuda/`.

Browser redeploy endpoints (each pulls the latest `main` first):

- `/redeploy.php?ponuda_only=1` builds only `/ponuda/` (`ponuda_2_only=1` is an alias).
- `/redeploy.php?ponuda_sara_ai_only=1` builds only `/ponuda-sara-ai/`.
- `/redeploy.php?offers_only=1` builds both offers without the main application build.

For local development, install dependencies in this directory and use its
`dev` script (add `--mode ponuda-sara-ai` for the SaraAI offer). The offer
project's `build` script builds both pages; `build:ponuda` and
`build:ponuda-sara-ai` build one each.
