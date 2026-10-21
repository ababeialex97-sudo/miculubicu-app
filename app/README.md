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
  - `cos` – coș și finalizarea comenzii (livrare/ridicare, punct de lucru, adresă, telefon, plată numerar), cu cont sau fără cont (prenume + telefon);
  - `comanda/[id]` – statusul comenzii, cu ora fiecărui pas (se reîncarcă la 30 s și la fiecare notificare);
  - `autentificare`, `locatie` – ferestre modale;
- `src/api/` – clientul HTTP, tipurile și hook-urile TanStack Query pentru `mlb-app-api`;
- `src/store/` – coșul (Zustand, salvat pe telefon), sesiunea și comenzile fără cont (tokenuri în keychain prin expo-secure-store);
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

## Comandă fără cont

Dacă restaurantul o permite (`guest_checkout` în `/config`), clientul neautentificat comandă cu prenume și telefon; numele de familie și emailul sunt opționale. Telefonul păstrează în keychain tokenul fiecărei comenzi (ultimele 20), deci vede statusul, primește notificări și o găsește în tabul Comenzi. Când intră apoi în cont, comenzile trec automat în contul lui (`/orders/claim`) și primește ștampilele pentru cele finalizate.

## Intrare cu Google și Apple

Butoanele apar doar în build-urile EAS (nu în Expo Go) și doar dacă sunt activate în WordPress (`social_login` în `/config`).

- **Apple** (doar pe iPhone): pornește singur după ce contul Apple Developer al clientului are capabilitatea „Sign in with Apple” pentru `ro.miculubicu.app` (EAS o activează la build). App Store cere „Continuă cu Apple” oricând aplicația oferă și Google.
- **Google**: în Google Cloud (contul clientului) se creează un ecran de consimțământ OAuth și trei clienți OAuth: „Web application”, „iOS” (bundle `ro.miculubicu.app`) și „Android” (pachet `ro.miculubicu.app` + amprenta SHA-1 a cheii de semnare din `eas credentials`; pentru build-urile din Google Play, și amprenta din Play Console › App signing). Apoi:
  - `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` și `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID` în `.env` / variabilele EAS (nu sunt secrete);
  - Client ID-ul „Web application” în WordPress › WooCommerce › Setări aplicație › Google.

Fără aceste ID-uri butonul Google rămâne ascuns.
