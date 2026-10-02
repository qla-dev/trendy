import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import PonudaSaraAi from './ponudaSaraAi.tsx';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <PonudaSaraAi />
  </StrictMode>,
);
