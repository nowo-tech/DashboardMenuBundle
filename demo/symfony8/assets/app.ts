import './bootstrap.ts';
import './app.css';

// Re-expose Stimulus app so dashboard menu bundle (and any code running after this entry) can find it.
if (typeof window !== 'undefined') {
  const w = window as unknown as { Stimulus?: unknown; $$stimulusApp$$?: unknown };
  w.Stimulus = w.Stimulus ?? w.$$stimulusApp$$;
}

console.log('Happy coding!');
