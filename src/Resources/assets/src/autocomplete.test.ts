import { describe, expect, it } from 'vitest';
import Stub from '../test-support/autocomplete';
import Entry from './autocomplete';

describe('autocomplete entry', () => {
  it('re-exports the UX Autocomplete controller as the default export', () => {
    expect(Entry).toBe(Stub);
  });
});
