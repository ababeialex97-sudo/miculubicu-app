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
| POST | `/auth/password-reset` | – | Trimite emailul standard de resetare a parolei |
| GET / PATCH | `/me` | token | Profilul clientului (nume, telefon, adresă) |
| GET | `/orders` | token | Istoricul comenzilor (paginat: `page`, `per_page`) |
| POST | `/orders` | token | Plasează o comandă |
| GET | `/orders/{id}` | token | O comandă, statusul ei și ora fiecărui pas |
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
  ]
}
```

`client_order_id` (generat în aplicație) face cererea sigură la reîncercare: aceeași valoare întoarce comanda deja creată.

## Cum se leagă de GrandChef

- Comanda e creată ca o comandă WooCommerce obișnuită (`created_via = mlb-app`), apoi trecută în `processing`, ceea ce declanșează plugin-ul GrandChef. Plugin-ul GrandChef nu e modificat.
- Prețurile vin mereu din WooCommerce, nu din aplicație.
- Preferințele pe produs se salvează în meta liniei de comandă cu cheia exactă `Preferinte`.
- Livrarea folosește metoda `flat_rate` din zona de livrare, iar ridicarea metoda `free_shipping`, cu titlurile lor exacte (GrandChef asociază după titlu). Livrarea gratuită peste prag rămâne pe metoda de livrare, cu cost 0.
- Înainte de trimiterea spre GrandChef, `default_socket_timeout` e setat la 10 secunde, ca o problemă de rețea să nu blocheze cererea până la 504.
- În modul de test (implicit activ) observația comenzii începe cu „COMANDA DE TEST”.

## Panoul pentru personal

În WordPress, sub **WooCommerce** apar două pagini noi:

- **Comenzi aplicație**: comenzile deschise din aplicație, cu clientul, telefonul, adresa, produsele cu preferințele lor, observațiile, totalul și dacă au ajuns la GrandChef (`_gc_order_id`). Butoanele mută comanda la pasul următor: Acceptă → Pune pe jar → Plecată la client / Gata de ridicare → Finalizată, plus Anulează. Pagina se reîncarcă singură la 20 de secunde și sună scurt la o comandă nouă. Filtrul de sus arată doar un punct de lucru sau toate comenzile de azi. Acces: utilizatorii care pot edita comenzi (`edit_shop_orders`), de exemplu Shop Manager.
- **Setări aplicație**: punctele de lucru (nume, adresă, telefon, livrare/ridicare, cost și prag pe punct), pragul general de livrare gratuită, metodele WooCommerce folosite pentru livrare și ridicare, modul de test.

Fiecare schimbare de status:
- se salvează cu ora ei (`_mlb_status_history`), iar aplicația o arată pe ecranul de status;
- adaugă o notă în comanda WooCommerce;
- „Finalizată” și „Anulată” mută și comanda WooCommerce în `completed` / `cancelled`; invers, o comandă finalizată sau anulată direct din WooCommerce se actualizează și în aplicație. Comanda nu mai trece niciodată prin `processing`, deci GrandChef nu o primește a doua oară;
- trimite clientului o notificare push.

## Notificări push

Plugin-ul trimite notificările prin Expo Push Service (`https://exp.host/--/api/v2/push/send`), la toate telefoanele pe care clientul e logat. Tokenurile pe care Expo le raportează ca neînregistrate se șterg automat. Opțional, pentru securitate suplimentară activată în contul Expo:

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

Locația aleasă de client se salvează în comandă (`_mlb_location_id`); rutarea spre serverul GrandChef al locației vine la pasul 6.

## Preferințe pe produs

În editorul de produs (WooCommerce › Produse › General) apare câmpul „Preferințe în aplicație”: câte o opțiune pe rând, de exemplu „Cu muștar”. Aplicația le arată ca butoane, iar alegerile clientului, împreună cu observația lui, ajung pe linia de comandă în meta `Preferinte`, deci la GrandChef ca notă pe produs. În `/menu` apar ca `preference_options`.

## Statusuri în aplicație

GrandChef nu transmite statusuri, așa că aplicația folosește meta `_mlb_status` (`received`, `confirmed`, `preparing`, `on_the_way`, `ready_for_pickup`, `completed`, `cancelled`), schimbat de personal din panoul „Comenzi aplicație”.

## Teste

```bash
php wordpress/mlb-app-api/tests/jwt-test.php
php wordpress/mlb-app-api/tests/push-messages-test.php
```
