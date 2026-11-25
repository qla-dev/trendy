# Ponuda

The offers share build dependencies but have separate entry points, stylesheets
and public folders.

- Main offer (Faze II, III i IV): `/ponuda/`
- SaraAI offer: `/ponuda-sara-ai/`
- ALKUS Orderflow.ai offer: `/ponuda-alkus/`

Source files, dependencies, and source PDFs live in `resources/ponuda`.
The main offer is defined in `src/App.tsx`, with its PDFs in `assets/`.
The SaraAI offer is defined in `ponuda-sara-ai/src/ponudaSaraAi.tsx`.
The ALKUS offer is defined in `ponuda-alkus/src/PonudaAlkus.tsx`.
Vite builds each offer under its matching folder in `public/`, with its own
assets directory. The generated directories are ignored by Git.

The former `/ponuda-2/` offer now lives at `/ponuda/`; the previous first offer
was retired. A ponuda redeploy replaces the old `public/ponuda-2/` build with a
redirect to `/ponuda/`.

Browser redeploy endpoints (each pulls the latest `main` first):

- `/redeploy.php?ponuda_only=1` builds only `/ponuda/` (`ponuda_2_only=1` is an alias).
- `/redeploy.php?ponuda_sara_ai_only=1` builds only `/ponuda-sara-ai/`.
- `/redeploy.php?offers_only=1` builds all offers without the main application build.

For local development, install dependencies in this directory and use its
`dev` script with `--mode ponuda-sara-ai` or `--mode ponuda-alkus` for the
corresponding offer. The `build` script builds all three pages. Run
`build:ponuda`, `build:ponuda-sara-ai` or `build:ponuda-alkus` to build one page.
