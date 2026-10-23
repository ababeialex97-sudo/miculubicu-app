# mlb-app-api

Plugin WordPress care expune API-ul aplicației mobile Micu' lu' Bicu, peste WooCommerce.
Namespace REST: `/wp-json/mlb/v1`. Necesită WordPress 6.5+, WooCommerce și PHP 8.1.

## Instalare

1. Copiază folderul `mlb-app-api` în `wp-content/plugins/` și activează plugin-ul.
2. Adaugă în `wp-config.php` o cheie de semnare pentru tokeni (recomandat; altfel plugin-ul generează una și o salvează în opțiuni):
   ```php
   define( 'MLB_APP_API_JWT_SECRET', '...un șir lung și aleator...' );
   ```
3. Dacă Apache elimină header-ul `Authorization`, adaugă în `.htaccess`:
   ```
   SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
   ```

## Endpoint-uri

| Metodă | Rută | Autentificare | Ce face |
| --- | --- | --- | --- |
| GET | `/menu` | – | Categorii și produse (poze, descrieri, gramaj, prețuri, variații) |
| GET | `/config` | – | Puncte de lucru, cost livrare, prag livrare gratuită, metode de plată, statusuri |
| POST | `/auth/register` | – | Cont nou de client WooCommerce; întoarce token + profil |
| POST | `/auth/login` | – | Autentificare cu email și parolă; întoarce token + profil |
| POST | `/auth/google` | – | Intrare cu Google: `id_token` de la Google Sign-In; creează contul la prima intrare sau îl leagă de contul cu același email |
| POST | `/auth/apple` | – | Intrare cu Apple: `identity_token` (+ `first_name`, `last_name` la prima intrare) |
| POST | `/auth/password-reset` | – | Trimite emailul standard de resetare a parolei |
| GET / PATCH | `/me` | token | Profilul clientului (nume, telefon, adresă) |
| DELETE | `/me` | token + parola (fără parolă la conturile făcute cu Google/Apple) | Șterge contul (cerință App Store / Google Play): contul și cupoanele personale; comenzile rămân în WooCommerce, fără client. Conturile de personal nu se pot șterge de aici |
| GET | `/orders` | token | Istoricul comenzilor (paginat: `page`, `per_page`) |
| POST | `/orders` | token | Plasează o comandă |
| GET | `/orders/{id}` | token | O comandă, statusul ei și ora fiecărui pas |
| POST | `/orders/guest` | – | Comandă fără cont: aceleași câmpuri ca `/orders`, plus `first_name` (obligatoriu), `last_name`, `email`; răspunsul conține `guest_token` |
| GET | `/orders/guest/{id}` | header `X-MLB-Order-Token` | O comandă fără cont |
| POST | `/orders/guest/lookup` | tokenurile comenzilor | Comenzile fără cont de pe telefon: `{"orders":[{"id":1,"token":"..."}]}` |
| POST | `/orders/guest/{id}/push-token` | header `X-MLB-Order-Token` | Notificări push pentru o comandă fără cont |
| POST | `/orders/claim` | token | Mută în cont comenzile fără cont de pe telefon (istoric + ștampile pentru cele deja finalizate) |
| GET | `/loyalty` | token | Cardul de fidelitate (ștampile) și cupoanele pe care clientul le poate folosi |
| POST | `/cart/preview` | token opțional | Verifică codurile promoționale și calculează reducerea, livrarea și totalul |
| GET | `/admin/orders` | personal (cookie WordPress) | Comenzile din aplicație pentru panou (`scope=active` sau `today`) |
| POST | `/admin/orders/{id}/status` | personal (cookie WordPress) | Schimbă statusul și trimite push |
| POST / DELETE | `/push-tokens` | token | Înregistrează / șterge tokenul Expo Push al dispozitivului |

Autentificarea folosește `Authorization: Bearer <token>` (JWT HS256, valabil 30 de zile). Schimbarea parolei invalidează toate tokenurile clientului.

### Exemplu de comandă

```json
POST /wp-json/mlb/v1/orders
{
  "client_order_id": "6f1c2c4e-1b5e-4a0e-9c55-3f1f6f1d2a10",
  "location_id": "loc1",
  "fulfillment": "delivery",
  "address": { "address_1": "Str. Exemplu 1", "city": "Vaslui" },
  "phone": "0740000000",
  "note": "Fără ceapă la tot",
  "payment_method": "cod",
  "items": [
    { "product_id": 9390, "quantity": 2, "preferences": "bine făcuți" }
  ],
  "coupon_codes": ["bicu-a1b2c3"]
}
```

`client_order_id` (generat în aplicație) face cererea sigură la reîncercare: aceeași valoare întoarce comanda deja creată.

## Plata cu cardul la livrare / la ridicare

Plugin-ul adaugă metoda de plată WooCommerce „Card la livrare / la ridicare” (`mlb_card`), dezactivată implicit: clientul plătește cu cardul la curier sau la casă, nimic online. Se activează din WooCommerce › Setări › Plăți și se asociază în pagina GrandChef cu tipul lor de plată pentru card. Când e activă, aplicația îi lasă clientului de ales între numerar și card.

## Comandă fără cont

Clientul poate comanda doar cu prenume, telefon și adresă (emailul e opțional). Comanda e o comandă WooCommerce de oaspete (fără client) și trece prin GrandChef exact ca celelalte.

- Odată cu comanda, aplicația primește un token aleator pe care îl păstrează în telefon; în comandă se salvează doar hash-ul lui (`_mlb_guest_token_hash`). Cu el, telefonul vede statusul comenzii și primește notificări (tokenurile push stau în comandă, `_mlb_push_tokens`).
- Dacă clientul intră apoi în cont (sau își face unul) pe același telefon, aplicația mută automat comenzile în cont (`/orders/claim`); cele deja finalizate primesc ștampila pe card.
- Limită: 6 comenzi fără cont pe oră de pe aceeași adresă IP (`CF-Connecting-IP`, site-ul fiind în spatele Cloudflare).
- Codurile „doar la prima comandă” și recompensele de fidelitate cer cont; codurile promoționale obișnuite merg și fără cont.
- Se poate opri din **Setări aplicație** › „Comandă fără cont”.

## Intrare cu Google și Apple

Aplicația primește de la Google sau Apple un ID token semnat (JWT RS256). Plugin-ul îl verifică cu cheile publice ale furnizorului (descărcate de la `googleapis.com` / `appleid.apple.com` și ținute 6 ore), apoi verifică emitentul, aplicația căreia i-a fost emis (`aud`) și expirarea.

- Contul se găsește după ID-ul de la furnizor (`_mlb_google_sub` / `_mlb_apple_sub`), apoi după emailul confirmat; altfel se creează un client WooCommerce nou, fără parolă proprie (`_mlb_social_only`). Dacă își setează mai târziu o parolă („Am uitat parola”), poate intra și cu email.
- Conturile de personal (orice rol în afară de customer/subscriber) nu pot intra așa.
- În **Setări aplicație** se completează Client ID-ul Google de tip „Web application” (același pe care aplicația îl folosește ca `webClientId`) și Bundle ID-ul iOS pentru Apple (implicit `ro.miculubicu.app`). Un câmp gol ascunde butonul din aplicație.

## Cum se leagă de GrandChef

- Comanda e creată ca o comandă WooCommerce obișnuită (`created_via = mlb-app`), apoi trecută în `processing`, ceea ce declanșează plugin-ul GrandChef. Plugin-ul GrandChef nu e modificat.
- Prețurile vin mereu din WooCommerce, nu din aplicație.
- Preferințele pe produs se salvează în meta liniei de comandă cu cheia exactă `Preferinte`.
- Livrarea folosește metoda `flat_rate` din zona de livrare, iar ridicarea metoda `free_shipping`, cu titlurile lor exacte (GrandChef asociază după titlu). Livrarea gratuită peste prag rămâne pe metoda de livrare, cu cost 0.
- Înainte de trimiterea spre GrandChef, `default_socket_timeout` e setat la 10 secunde, ca o problemă de rețea să nu blocheze cererea până la 504.
- După fiecare trimitere spre GrandChef (din aplicație sau de pe site) plugin-ul scrie o notă în comandă: „GrandChef: trimisă în X s, comanda GrandChef #…” sau „GrandChef: NETRIMISĂ…”, cu avertismentele PHP apărute (conexiune refuzată, timeout etc.). Același text ajunge în WooCommerce › Stare › Jurnale, sursa `mlb-grandchef`.
- Plugin-ul GrandChef nu mai retrimite o comandă odată marcată (`_added_to_gc`), nici dacă trimiterea a eșuat. Pentru o comandă „În procesare” fără număr GrandChef, în pagina comenzii apare acțiunea „Retrimite la GrandChef”.
- În modul de test (implicit activ) observația comenzii începe cu „COMANDA DE TEST”.

## Panoul pentru personal

În WordPress, sub **WooCommerce** apar două pagini noi:

- **Comenzi aplicație**: comenzile deschise din aplicație, cu clientul, telefonul, adresa, produsele cu preferințele lor, observațiile, totalul și dacă au ajuns la GrandChef (`_gc_order_id`). Butoanele mută comanda la pasul următor: Acceptă → Pune pe jar → Plecată la client / Gata de ridicare → Finalizată, plus Anulează. Pagina se reîncarcă singură la 20 de secunde și sună scurt la o comandă nouă. Filtrul de sus arată doar un punct de lucru sau toate comenzile de azi. Acces: utilizatorii care pot edita comenzi (`edit_shop_orders`), de exemplu Shop Manager.
- **Setări aplicație**: punctele de lucru (nume, adresă, telefon, livrare/ridicare, cost și prag pe punct), pragul general de livrare gratuită, metodele WooCommerce folosite pentru livrare și ridicare, cardul de fidelitate, modul de test.

Fiecare schimbare de status:
- se salvează cu ora ei (`_mlb_status_history`), iar aplicația o arată pe ecranul de status;
- adaugă o notă în comanda WooCommerce;
- „Finalizată” și „Anulată” mută și comanda WooCommerce în `completed` / `cancelled`; invers, o comandă finalizată sau anulată direct din WooCommerce se actualizează și în aplicație. Comanda nu mai trece niciodată prin `processing`, deci GrandChef nu o primește a doua oară;
- trimite clientului o notificare push.

## Fidelizare și promoții

Totul e construit peste cupoanele WooCommerce, deci reducerile ajung la GrandChef prin opțiunea „Spread discount”, împărțite pe produse.

- **Coduri promoționale, reduceri pe produse sau categorii, promoții pe interval**: cupoane WooCommerce obișnuite (Marketing › Cupoane), cu restricțiile lor de produse, categorii, sumă minimă și dată de expirare. Pe ecranul cuponului apar două bife noi:
  - „Afișează în aplicație”: cuponul apare în ecranul Fidelitate, cu descrierea lui, și clientul îl aplică dintr-o atingere;
  - „Doar la prima comandă”: valabil doar pentru clienții fără nicio comandă anterioară (merge și pe site).
- **Card de fidelitate**: fiecare comandă din aplicație ajunsă „Finalizată” adaugă o ștampilă (o singură dată per comandă). La cardul plin (implicit 4) clientul primește un cupon personal `bicu-xxxxxx`, de o singură folosință, legat de emailul lui, plus o notificare push. Numărul de comenzi, reducerea (procent sau lei) și valabilitatea se schimbă din **Setări aplicație**.
- **Livrare gratuită peste prag**: din **Setări aplicație** (general sau pe punct de lucru); pragul se compară cu valoarea produselor înainte de reducere.

O comandă primește cel mult două coduri (de exemplu un cod promoțional și recompensa de fidelitate), dacă regulile cupoanelor permit combinarea lor. Codurile se verifică înainte de crearea comenzii, deci un cod greșit nu lasă comenzi pe jumătate făcute.

## Notificări push

Plugin-ul trimite notificările prin Expo Push Service (`https://exp.host/--/api/v2/push/send`), la toate telefoanele pe care clientul e logat (la comenzile fără cont, la telefonul care a plasat comanda). Tokenurile pe care Expo le raportează ca neînregistrate se șterg automat. Opțional, pentru securitate suplimentară activată în contul Expo:

```php
define( 'MLB_APP_API_EXPO_ACCESS_TOKEN', '...' );
```

Erorile de trimitere ajung în WooCommerce › Status › Logs (sursa `mlb-app-api`) și nu blochează schimbarea statusului.

## Setări (opțiuni WordPress)

Se editează din **WooCommerce › Setări aplicație**; dedesubt, opțiunile folosite:

| Opțiune | Implicit | Rol |
| --- | --- | --- |
| `mlb_app_api_locations` | două puncte de lucru generice (`loc1`, `loc2`) | listă de puncte de lucru (`id`, `name`, `address`, `phone`, `delivery`, `pickup`, opțional `delivery_fee`, `free_delivery_threshold`) |
| `mlb_app_api_free_delivery_threshold` | `0` (dezactivat) | prag de livrare gratuită |
| `mlb_app_api_test_mode` | `yes` | marchează comenzile cu „COMANDA DE TEST” |
| `mlb_app_api_delivery_instance_id` | prima metodă `flat_rate` activă | metoda WooCommerce pentru livrare |
| `mlb_app_api_pickup_instance_id` | prima metodă `free_shipping` activă | metoda WooCommerce pentru ridicare |
| `mlb_app_api_guest_checkout` | `yes` | comandă fără cont |
| `mlb_app_api_google_client_ids` | gol (dezactivat) | Client ID-uri Google acceptate, câte unul pe rând |
| `mlb_app_api_apple_client_ids` | `ro.miculubicu.app` | Bundle ID-uri acceptate pentru Sign in with Apple |
| `mlb_app_api_loyalty` | activ, 4 comenzi, 10%, 60 de zile | cardul de fidelitate (`enabled`, `required`, `reward_type` `percent`/`fixed`, `reward_amount`, `reward_days`) |

Locația aleasă de client se salvează în comandă (`_mlb_location_id`); rutarea spre serverul GrandChef al locației vine la pasul 6.

## Preferințe pe produs

În editorul de produs (WooCommerce › Produse › General) apare câmpul „Preferințe în aplicație”: câte o opțiune pe rând, de exemplu „Cu muștar”. Aplicația le arată ca butoane, iar alegerile clientului, împreună cu observația lui, ajung pe linia de comandă în meta `Preferinte`, deci la GrandChef ca notă pe produs. În `/menu` apar ca `preference_options`.

## Statusuri în aplicație

GrandChef nu transmite statusuri, așa că aplicația folosește meta `_mlb_status` (`received`, `confirmed`, `preparing`, `on_the_way`, `ready_for_pickup`, `completed`, `cancelled`), schimbat de personal din panoul „Comenzi aplicație”.

## Teste

```bash
php wordpress/mlb-app-api/tests/jwt-test.php
php wordpress/mlb-app-api/tests/push-messages-test.php
php wordpress/mlb-app-api/tests/loyalty-rules-test.php
php wordpress/mlb-app-api/tests/id-token-test.php
php wordpress/mlb-app-api/tests/guest-orders-test.php
```
