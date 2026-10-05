# Aplicația Micu' lu' Bicu (Expo)

Aplicația mobilă pentru clienți (Android + iOS), construită cu Expo, expo-router și TypeScript strict.
Vorbește doar cu API-ul din plugin-ul WordPress `mlb-app-api` (`/wp-json/mlb/v1`), niciodată direct cu GrandChef.

## Pornire

```bash
cd app
npm install
cp .env.example .env   # setează EXPO_PUBLIC_API_URL
npx expo start
```

## Verificări

```bash
npx tsc --noEmit
npx expo lint
```

## Structură

- `src/app/` – doar ecrane și layout-uri (expo-router);
- `src/constants/theme.ts` – culori și fonturi din machetă;
- restul codului (componente, hook-uri, client API) stă în `src/`, în afara `src/app/`.

Ecranele (meniu, produs, coș, checkout, status) se construiesc la pasul 3 din `CLAUDE.md`.
