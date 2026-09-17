import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import Ponuda2 from './ponuda2.tsx';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <Ponuda2 />
  </StrictMode>,
);
