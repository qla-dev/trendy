import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import {defineConfig, loadEnv} from 'vite';

export default defineConfig(({mode}) => {
  const env = loadEnv(mode, '.', '');
  // Each extra offer lives in its own folder and builds into public/<mode>/
  const extraOffers = ['ponuda-2', 'ponuda-sara-ai'];
  const offerFolder = extraOffers.includes(mode) ? mode : null;
  const offerRoot = offerFolder ? path.resolve(__dirname, offerFolder) : __dirname;
  return {
    root: offerRoot,
    base: './',
    plugins: [react(), tailwindcss()],
    build: {
      outDir: path.resolve(__dirname, `../../public/${offerFolder ?? 'ponuda'}`),
      emptyOutDir: true,
      rollupOptions: {
        input: {
          index: path.resolve(offerRoot, 'index.html'),
        },
      },
    },
    define: {
      'process.env.GEMINI_API_KEY': JSON.stringify(env.GEMINI_API_KEY),
    },
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    server: {
      // HMR is disabled in AI Studio via DISABLE_HMR env var.
      // Do not modifyâfile watching is disabled to prevent flickering during agent edits.
      hmr: process.env.DISABLE_HMR !== 'true',
    },
  };
});
