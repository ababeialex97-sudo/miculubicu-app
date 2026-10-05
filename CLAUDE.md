# Micu' lu' Bicu – aplicație mobilă de comenzi (Android + iOS)

Context de proiect pentru Claude Code. Citește tot fișierul înainte de a scrie cod.
Limba de comunicare cu dezvoltatorul (Alex): **română**. Codul și comentariile din cod: engleză.

## 1. Ce construim

Aplicație mobilă proprie de comenzi pentru restaurantul **Micu' lu' Bicu** (Vaslui; mici, fripturi pe jar, produse din abator propriu), plus backend-ul de care are nevoie.

- Site existent: https://miculubicu.ro (WordPress + Elementor, tema Bouffe, WooCommerce instalat, magazin în modul „În curând”).
- Comenzile ajung în programul de gestiune **GrandChef** al restaurantului, prin plugin-ul lor pentru WooCommerce (vezi secțiunea 4).
- Meniu mic: ~20 de produse (mici oaie-porc, mici porc, mici cruzi, mici Angus-Mangaliță, fripturi/ceafă, cârnăciori, crispy pui, cartofi, pastramă de oaie, sosuri etc.).
- **Două puncte de lucru** pentru livrare/ridicare, fiecare cu propriile date de conectare GrandChef.

### Funcții (pachetul complet vândut clientului)

Aplicație client:
- meniu pe categorii (poze, descrieri, gramaje, prețuri), opțiuni/preferințe pe produs, observații pentru bucătărie;
- coș, livrare sau ridicare, alegerea punctului de lucru, adresă, cost livrare, prag livrare gratuită;
- plată: numerar la livrare / la ridicare (card online = opțional, ofertat separat);
- cont client, istoric comenzi, „comandă din nou”;
- status comandă + notificări push;
- fidelizare simplă și promoții (secțiunea 6).

Panou de administrare (web):
- comenzi noi + schimbarea statusului (GrandChef NU transmite statusuri – personalul le schimbă din panou și pleacă push către client);
- promoții, cupoane, reguli de fidelizare, costuri livrare, prag livrare gratuită, notificări către clienți.

## 2. Arhitectură recomandată

```
Aplicație Expo (React Native, TypeScript)
        │  HTTPS, JSON, autentificare client (JWT)
        ▼
WordPress + WooCommerce (miculubicu.ro)
  └─ plugin propriu „mlb-app-api” (namespace REST: /wp-json/mlb/v1)
        │  creează comenzi WooCommerce → status „processing”
        ▼
Plugin GrandChef (existent) ── TCP 2003 ──▶ server GrandChef al locației
```

Principii:
- **Aplicația nu vorbește NICIODATĂ direct cu GrandChef.** Protocolul GrandChef trimite parola în clar pe TCP; doar serverul o folosește.
- **WooCommerce este sursa unică**: același meniu, aceleași comenzi și aceiași clienți pentru site și aplicație.
- Comenzile din aplicație devin comenzi WooCommerce obișnuite; plugin-ul GrandChef le trimite mai departe automat când trec în `processing`.
- Logica nouă (API pentru aplicație, fidelizare, statusuri, push, rutarea pe locații) stă într-un **plugin WordPress propriu**, versionat în acest repo. Nu modificăm tema și, pe cât posibil, nu modificăm plugin-ul GrandChef.

Stack aplicație (propunere, de confirmat la început):
- Expo + expo-router, TypeScript strict;
- TanStack Query pentru date, Zustand (sau context) pentru coș;
- expo-notifications pentru push (Expo Push Service);
- expo-secure-store pentru token;
- build și publicare cu EAS (nu e nevoie de Mac pentru iOS).

## 3. Serverul (producție)

- Hetzner, administrat prin **RunCloud**, acces SSH (Alex folosește Termius). Server: „Server2”.
- Web app: `/home/runcloud/webapps/miculubicu`, stack NGINX + Apache hybrid, **PHP 8.1**, rulează ca user `runcloud`.
- `open_basedir`: `/home/runcloud/webapps/miculubicu:/var/lib/php/session:/tmp`.
- `disable_functions` include toate `socket_*` (socket_connect, socket_read etc.), `proc_open`, `exec`, `shell_exec`, `stream_socket_server`... **`fsockopen` și `stream_socket_client` sunt permise.**
- `max_execution_time` = 30s.
- CLI PHP: `/RunCloud/Packages/php81rc/bin/php`. Atenție: încărcarea `wp-load.php` din CLI dă „eroare critică” (un plugin nu suportă CLI) – pentru diagnostic se folosesc scripturi web temporare care se auto-șterg.
- Pe același server mai sunt și alte site-uri (sursadeiasi.ro, tocmeli.ro) – nu le atinge.
- Site-ul e în spatele Cloudflare (plugin „Flexible SSL for CloudFlare”).

## 4. Integrarea GrandChef – ce știm sigur (verificat)

Plugin instalat: „Grand Chef Settings” v1.3 (folder `grandchef-plugin-3.1.2hf`), identic cu arhiva primită de la GrandChef (verificat prin md5). Documentația: „Protocol comenzi online GrandChef V2” + „Grandchef plugin V2”.

Protocol (TCP, port 2003, câte o conexiune nouă pentru fiecare apel, Java DataInput/OutputStream: string = 2 bytes lungime + UTF, int = 4 bytes big-endian):
- `CreateOrderV2`, `CreateOrderItem`, `InsertOrderPref` (trimitere comenzi);
- `GetMeasureUnits`, `GetCategories`, `GetPreferences`, `GetProducts`, `GetDeliveryTypes`, `GetPaymentTypes` (nomenclatoare);
- **NU există** citire status comandă, clienți, fidelizare, stoc/disponibilitate, poze sau descrieri.

Comportamentul plugin-ului (din cod):
- trimite comanda pe hook-ul `woocommerce_order_status_processing`; salvează în comandă `_gc_order_id`, `_gc_order_success`, `_added_to_gc`;
- citește din produs meta `wccaf_id_grandchef` și `wccaf_cota_tva` (la variații `wccaf_id_grandchef_v`, `wccaf_cota_tva_v`);
- citește preferințele din meta liniei de comandă cu cheia exactă `Preferinte` (trimise ca note pe produs);
- metoda de livrare se asociază după **titlul** metodei WooCommerce (`get_shipping_method()`); în pagina de asociere apare o singură opțiune per tip de metodă (`flat_rate`, `free_shipping`...), deci două metode de livrare trebuie să fie de tipuri diferite;
- metoda de plată se asociază după gateway;
- opțiunea „Spread discount” împarte reducerea comenzii pe produse înainte de trimitere → reducerile din fidelizare/cupoane ajung corect pe bon;
- **„Connection Type” trebuie să rămână bifat** (folosește `fsockopen`; metoda standard folosește `socket_*`, dezactivate pe server);
- `fsockopen` fără timeout și bucla de citire din `Connection::_read` nu au limită → la probleme de rețea cererea atârnă până la 504. Dacă ne atingem de această zonă, setăm `default_socket_timeout` din codul nostru, nu modificăm plugin-ul;
- import produse: fereastră + `alert()` în browser; AJAX `custom_button_action` (item_id, item_name, item_price, item_vat). Pune doar nume, preț și meta – fără poze/descrieri/categorii.

Configurație curentă (TEST):
- endpoint `82.79.134.83:2003`, Site ID `31`; parola e salvată doar în WordPress (opțiunea `grandchef_password`) – **nu o pune în repo**;
- baza de test GrandChef e un restaurant demonstrativ (189 produse: ciorbe, salate...), NU meniul real;
- livrare (zona „România / Vaslui”): „Livrare la domiciliu” (flat_rate, 10 lei provizoriu) ↔ GC 4 „delivery”; „Ridicare personală” (free_shipping) ↔ GC 3 „ridicare personala”; o metodă flat_rate duplicat „Ridicare personală” e dezactivată;
- plată: „Numerar la livrare” (cod) ↔ GC 1 „NUMERAR”;
- produse de test importate: CIORBA DE GAINA (WC 9390, GC 3), MUSTAR (WC 9391, GC 13);
- comandă de test reușită: WC #9392 → GrandChef #315320.

**Producție:** fiecare locație are propriile date (IP, Site ID, parolă), primite de la GrandChef la final. Plugin-ul suportă o singură conexiune → trebuie construită **rutarea pe locație** (comanda merge la serverul locației alese de client). De clarificat cu GrandChef dacă ID-urile produselor sunt aceleași în ambele locații.

## 5. Plugin-ul „grandchef-campuri” (deja scris)

WC Fields Factory 4.1.12 generează chei aleatoare pentru câmpuri, deci metoda din documentația GrandChef nu funcționează. Am scris un plugin mic, `grandchef-campuri`, care adaugă câmpurile ID GrandChef / Cota TVA (produse simple și variații) cu cheile exacte și câmpul „Preferințe” pe pagina produsului, salvat ca meta `Preferinte` pe linia de comandă. De verificat că e instalat; dacă nu, sursa e în `wordpress/grandchef-campuri/` (adaug-o în repo).

## 6. Fidelizare și promoții (reguli din ofertă)

Simple, configurabile din panou, fără sistem complex de recompense:
- după **4 comenzi finalizate** → reducere la următoarea comandă (card cu 4 „ștampile” + recompensă);
- livrare gratuită peste un prag de valoare;
- reducere la prima comandă;
- coduri promoționale;
- reduceri pe produse sau categorii;
- promoții active într-un interval de timp.

Recomandare: implementarea peste cupoanele WooCommerce (generate automat per client), ca reducerea să treacă prin „Spread discount” spre GrandChef.

## 7. Design

Machetă (5 ecrane: Meniu, Produs, Coș, Status comandă, Fidelitate): artefact „Aplicație Micu' lu' Bicu” în contul claude.ai al lui Alex.
- fundal cărbune `#17120F`, suprafețe `#241C17`, chenare `#3A2E25`, text `#F6EEE3`, text secundar `#BFAF9E`;
- roșu jar `#C8321F` (butoane principale), galben-muștar `#F2B705` (accente, prețuri, fidelitate);
- fonturi: Zilla Slab (titluri), Figtree (text);
- ținte de atingere ≥ 44px; contrast AA.
Pozele reale ale produselor sunt pe site (`/wp-content/uploads/2025/09/...`).

## 8. Reguli de lucru

- Secretele (parole GrandChef, chei, tokeni) doar în `.env` / opțiuni WordPress / EAS secrets. Niciodată în cod, în commit-uri sau în chat.
- Nu modifica plugin-ul GrandChef; extinde prin hook-uri din plugin-ul propriu.
- Înainte de orice modificare pe serverul de producție: backup și confirmare de la Alex.
- Testele de comandă se fac doar pe conexiunea GrandChef de TEST, cu notă „COMANDA DE TEST”.
- Conturile Apple Developer și Google Play se fac pe firma clientului.
- Termen ofertat: 3–5 săptămâni de la primirea materialelor și a acceselor.

## 9. Ordinea propusă

1. Repo + structură (`app/` Expo, `wordpress/mlb-app-api/`, `wordpress/grandchef-campuri/`).
2. `mlb-app-api`: endpoint-uri meniu, autentificare client, creare comandă, istoric, status, înregistrare token push.
3. Aplicația: meniu → produs → coș → checkout → status (după machetă).
4. Panou: comenzi + schimbare status + push.
5. Fidelizare și promoții.
6. Rutarea pe cele două locații (după datele de producție de la GrandChef).
7. Build EAS, TestFlight / test intern Google Play, publicare.

## 10. Întrebări deschise

- ID-urile produselor sunt identice în ambele locații GrandChef?
- Cum alege clientul locația: automat după adresă/zonă sau manual?
- Costul real al livrării și pragul de livrare gratuită, pe locație.
- Se emite bon fiscal din GrandChef la comenzile online? (de confirmat cu contabilul / GrandChef)
- Plată cu cardul online: da/nu și procesatorul ales.
- Valoarea reducerii de fidelitate (procent sau sumă fixă).
