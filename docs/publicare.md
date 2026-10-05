# Build și publicare (pasul 7)

Ghid pas cu pas pentru build-urile EAS, testare internă (TestFlight / Google Play) și publicare.
Nu e nevoie de Mac: build-urile iOS rulează pe serverele Expo.

## 1. Ce trebuie de la client, înainte de orice

Conturile se fac **pe firma clientului** (CLAUDE.md §8), nu pe numele dezvoltatorului.

| Ce | Cost | Observații |
| --- | --- | --- |
| Apple Developer Program, cont de **organizație** | 99 USD / an | Cere număr D-U-N-S pentru firmă (gratuit, dar durează de la câteva zile la 2 săptămâni). Apoi Alex e invitat cu rol „App Manager” sau „Admin”. |
| Google Play Console, cont de **organizație** | 25 USD o singură dată | Verificarea firmei (D-U-N-S, act de identitate, site). Alex e invitat ca utilizator cu drept de publicare. |
| Cont Expo (organizație „miculubicu”) | gratuit (planul Free ajunge la început) | Alex e membru cu rol Admin. Aici stau build-urile și cheile de push. |
| Proiect Firebase | gratuit | Necesar doar pentru notificările push pe Android (FCM). |
| Pagină „Politica de confidențialitate” pe site | – | Ciornă în [politica-confidentialitate.md](politica-confidentialitate.md). Ambele magazine cer linkul. |
| Pagină / email pentru ștergerea contului | – | Google cere un link web unde clientul poate cere ștergerea contului și fără aplicație. |
| Logo vectorial (SVG/PDF) sau PNG de minimum 1024 px | – | Iconițele sunt făcute din logo-ul rotund primit (500 px); iconița iOS de 1024 px e mărită din el, deci ușor moale. |
| Poze reale ale produselor, încărcate în WooCommerce | – | Aplicația le ia din WooCommerce. |

## 2. Unelte

```bash
npm install -g eas-cli      # versiunea >= 24
eas login                   # cu contul Expo al lui Alex (membru în organizația clientului)
```

## 3. Legarea proiectului de Expo (o singură dată)

```bash
cd app
eas init --id <ID>          # sau `eas init` și alegi organizația clientului
```

Comanda scrie `extra.eas.projectId` și `owner` în `app.json`. Commit-ul acestei modificări e în regulă (nu e secret).
De abia de acum aplicația poate primi notificări push (până acum înregistrarea era sărită).

## 4. Android

### 4.1 Firebase (pentru push)

1. În [Firebase Console](https://console.firebase.google.com) creezi proiectul „Micu lu Bicu”.
2. Adaugi o aplicație Android cu pachetul `ro.miculubicu.app` și descarci `google-services.json`.
3. Îl urci în EAS ca variabilă de tip fișier (fișierul **nu** intră în git; `app.config.js` îl citește):
   ```bash
   eas env:create --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json \
     --visibility secret --environment preview --environment production
   ```
4. În Firebase › Project settings › Service accounts generezi o cheie privată (JSON) și o urci la Expo:
   `eas credentials` › Android › production › Google Service Account › „Manage your FCM V1 service account key”.

### 4.2 Build de test (APK, se instalează direct pe telefon)

```bash
eas build -p android --profile preview
```

Linkul primit la final se deschide pe telefon și instalează aplicația. EAS generează și păstrează singur cheia de semnare (keystore). Nu o șterge din contul Expo: fără ea nu mai poți publica actualizări.

### 4.3 Google Play, testare internă

1. În Play Console creezi aplicația „Micu' lu' Bicu”, limba implicită română, tip Aplicație, Gratuită.
2. Build de producție (AAB):
   ```bash
   eas build -p android --profile production
   ```
3. **Primul** AAB se urcă manual (Google nu permite primul upload prin API): Testing › Internal testing › Create release › încarci fișierul `.aab` descărcat din pagina build-ului.
4. Pentru următoarele versiuni, cu o cheie de service account Google Play (`google-play-service-account.json` în `app/`, ignorată de git):
   ```bash
   eas submit -p android --profile production   # urcă pe „internal”, ca draft
   ```
5. Completezi în Play Console: fișa magazinului (texte în [magazine.md](magazine.md)), Data safety (secțiunea 6), clasificarea conținutului, publicul țintă (18+ nu e necesar; 13+ e ok), linkul de ștergere a contului, datele de contact.

## 5. iOS

1. În App Store Connect creezi aplicația: nume „Micu' lu' Bicu”, bundle ID `ro.miculubicu.app`, SKU `miculubicu`, limba principală română.
2. Build:
   ```bash
   eas build -p ios --profile production
   ```
   Te loghezi cu contul Apple și răspunzi „Yes” când EAS se oferă să genereze certificatul de distribuție, profilul și **cheia de push (APNs)**. Le păstrează EAS.
3. Trimitere în TestFlight:
   ```bash
   eas submit -p ios --latest
   ```
4. În TestFlight adaugi testeri interni (personalul restaurantului, Alex), care instalează aplicația din aplicația TestFlight.

## 6. Ce declari la confidențialitate (ambele magazine)

Date colectate, toate legate de contul clientului, folosite doar pentru funcționarea aplicației (nu pentru publicitate, fără tracking):

- nume, email, telefon, adresa de livrare: cont și livrarea comenzii;
- istoricul comenzilor și ștampilele de fidelitate: funcționalitatea aplicației;
- token de notificări push: statusul comenzii.

Nu se colectează locația GPS, contacte, poze sau date de plată (plata e numerar). Datele sunt criptate în tranzit (HTTPS). Clientul își poate șterge contul din aplicație (Cont › Șterge contul). Comenzile rămân în WooCommerce pentru evidența contabilă, fără legătură cu contul.

Furnizori: găzduirea site-ului (Hetzner), Expo / Firebase / Apple pentru livrarea notificărilor.

## 7. Review la Apple și Google: atenție la comenzi reale

Recenzenții pot plasa o comandă. Dacă site-ul e legat de GrandChef-ul de producție, comanda ajunge în bucătărie.

- Dă-le un cont demo (de exemplu `review@miculubicu.ro`) în „App Review Information” / „App access”.
- În notă scrie: „Plata se face numerar la livrare. Vă rugăm să nu trimiteți comenzi: comenzile ajung direct în bucătăria restaurantului. Pentru test puteți folosi coșul, codul promoțional și ecranul de fidelitate.”
- Cel mai sigur: trimiți la review cât timp site-ul e pe conexiunea GrandChef de TEST, cu modul de test bifat în „Setări aplicație”.

## 8. Versiuni

- Numărul de versiune afișat (`1.0.0`) e în `app.json` › `version`; îl schimbi la fiecare lansare pentru clienți.
- Numerele de build (iOS `buildNumber`, Android `versionCode`) le ține EAS (`appVersionSource: remote`) și le crește singur la fiecare build de producție.

## 9. Profile din `eas.json`

| Profil | Rezultat | Folosit pentru |
| --- | --- | --- |
| `preview` | APK Android / build iOS ad-hoc | instalare directă pe telefoane de test |
| `production` | AAB / IPA pentru magazine | Google Play și App Store (TestFlight) |

Ambele folosesc `EXPO_PUBLIC_API_URL=https://miculubicu.ro/wp-json/mlb/v1`. Pentru un site de test, schimbi valoarea în `eas.json` sau creezi un profil nou.
