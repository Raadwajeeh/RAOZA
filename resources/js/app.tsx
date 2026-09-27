import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
createInertiaApp({
  title: (title) => title ? `${title} — RAOZA` : 'RAOZA',
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true }) as Record<string, { default: React.ComponentType }>;
    return pages[`./Pages/${name}.tsx`];
  },
  setup({ el, App, props }) { createRoot(el).render(<App {...props} />); },
});
