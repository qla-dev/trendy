import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import PonudaAlkus from './PonudaAlkus.tsx';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <PonudaAlkus />
  </StrictMode>,
);
