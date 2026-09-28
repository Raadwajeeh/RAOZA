import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

createInertiaApp({
  title: (title) => !title ? 'RAOZA' : title.toUpperCase().includes('RAOZA') ? title : `${title} — RAOZA`,
  resolve: (name) => pages[`./Pages/${name}.tsx`]?.(),
  setup({ el, App, props }) { createRoot(el).render(<App {...props} />); },
});
