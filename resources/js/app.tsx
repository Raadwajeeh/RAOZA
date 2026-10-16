import '../css/app.css';
import '@fontsource/plus-jakarta-sans/latin-400.css';
import '@fontsource/plus-jakarta-sans/latin-500.css';
import '@fontsource/plus-jakarta-sans/latin-600.css';
import '@fontsource/plus-jakarta-sans/latin-700.css';
import '@fontsource/playfair-display/latin-400.css';
import '@fontsource/playfair-display/latin-400-italic.css';
import { createInertiaApp, router } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

function NavigationProgress() {
  const [active, setActive] = useState(false);
  useEffect(() => {
    const stopStart = router.on('start', () => setActive(true));
    const stopFinish = router.on('finish', () => setActive(false));
    return () => { stopStart(); stopFinish(); };
  }, []);
  return active ? <div className="raoza-route-progress" role="progressbar" aria-label="Loading page" /> : null;
}

createInertiaApp({
  progress: false,
  title: (title) => !title ? 'RAOZA' : title.toUpperCase().includes('RAOZA') ? title : `${title} — RAOZA`,
  resolve: (name) => pages[`./Pages/${name}.tsx`]?.(),
  setup({ el, App, props }) { createRoot(el).render(<><NavigationProgress /><App {...props} /></>); },
});
