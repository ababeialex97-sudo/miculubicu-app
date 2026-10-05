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
npm test          # teste pentru logica coșului și prețuri (Node, fără emulator)
```

## Structură

- `src/app/` – doar ecrane și layout-uri (expo-router):
  - `(tabs)/` – Meniu, Comenzi (istoric + „Comandă din nou”), Fidelitate (card cu ștampile, cupoane), Cont (profil, politica de confidențialitate, ștergerea contului);
  - `produs/[id]` – produs cu variante, preferințe, observații și cantitate;
  - `cos` – coș și finalizarea comenzii (livrare/ridicare, punct de lucru, adresă, telefon, plată numerar);
  - `comanda/[id]` – statusul comenzii, cu ora fiecărui pas (se reîncarcă la 30 s și la fiecare notificare);
  - `autentificare`, `locatie` – ferestre modale;
- `src/api/` – clientul HTTP, tipurile și hook-urile TanStack Query pentru `mlb-app-api`;
- `src/store/` – coșul (Zustand, salvat pe telefon) și sesiunea (token în keychain prin expo-secure-store);
- `src/lib/` – logică fără UI (prețuri în bani, coș, statusuri), cu teste;
- `src/components/` – componente comune (butoane, câmpuri, iconițe din machetă);
- `src/constants/theme.ts` – culori și fonturi din machetă.

Prețurile afișate în coș sunt o estimare; totalul final îl calculează serverul.

## Build și publicare

Build-urile se fac cu EAS (`eas.json`: profilele `preview` și `production`); pașii completi, conturile necesare și textele pentru magazine sunt în [`docs/publicare.md`](../docs/publicare.md).
ID-uri: `ro.miculubicu.app` (iOS și Android). Iconițele, ecranul de pornire și iconița notificărilor sunt generate din `assets/images/logo.png` (logo-ul rotund, 500 × 500 px). Cu un logo vectorial sau de minimum 1024 px iconița iOS ar fi mai clară; se regenerează la fel.

## Notificări push

Aplicația cere permisiunea după prima comandă și înregistrează telefonul la `mlb-app-api` (`/push-tokens`); la ieșirea din cont îl șterge. Atingerea unei notificări deschide comanda.
Expo Push are nevoie de ID-ul proiectului EAS: rulează o dată `npx eas init` (cu contul Expo al clientului), care îl scrie în `app.json`. Până atunci înregistrarea e sărită fără erori. Pe Android mai trebuie `google-services.json` (Firebase), dat prin variabila EAS `GOOGLE_SERVICES_JSON` (vezi `app.config.js`). Pe web și în simulator nu există notificări push.
