import type { Config, Location } from '@/api/types';

/** The location the customer picked, falling back to the first one. */
export function resolveLocation(config: Config | undefined, locationId: string | null): Location | undefined {
  if (!config) {
    return undefined;
  }
  return config.locations.find((l) => l.id === locationId) ?? config.locations[0];
}
