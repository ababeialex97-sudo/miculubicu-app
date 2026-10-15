# Tema „Micu' lu' Bicu” (WordPress + Elementor)

Temă ușoară pentru miculubicu.ro, în stilul aprobat: fundal gri-cărbune, roșu jar, galben-muștar, fonturile Barlow Condensed (titluri) și Barlow (text). Înlocuiește tema Bouffe. Elementor și WooCommerce rămân.

Fiecare secțiune a site-ului este un widget Elementor din categoria **Micu' lu' Bicu**. Toate textele, pozele, butoanele, listele și culorile lor se schimbă din panoul Elementor. Textele se pot edita și direct, cu clic pe ele în previzualizare.

| Widget | Ce conține |
| --- | --- |
| Bandă promoție | reducerea la prima comandă, codul, pragul de livrare gratuită |
| Antet site | logo, meniu, telefon, buton coș cu total live, meniu pe telefon |
| Prima secțiune (hero) | nota Google, titlu, butoane, caseta „Livrăm la tine?”, poză, card fidelitate |
| Avantaje | banda cu iconițe (abator propriu, fără aditivi...) |
| Produse din meniu | produsele din WooCommerce, cu taburi pe categorii și „Adaugă” fără reîncărcarea paginii; tot el afișează și pachetele |
| Poză și text | Abator, Povestea noastră, Hotel... |
| Locații | poză, adresă, program, „Sună”, „Navighează” |
| Recenzii | recenzii reale, cu nota |
| Bandă aplicație | App Store / Google Play și macheta cardului de fidelitate |
| Subsol site | coloane editabile, rețele sociale, pictograme ANPC/SOL, date firmă |
| Bară coș (telefon) | bara fixă „Vezi coșul” de jos, doar pe telefon |
| Titlu de secțiune | supratitlu, titlu și text, pentru secțiuni noi |

## Instalare (pe o copie de test a site-ului, apoi pe producție)

1. **Backup** complet (fișiere + bază de date) din RunCloud.
2. Aspect › Teme › Adaugă › Încarcă tema › `mlb-theme.zip` › Activează.
3. Aspect › **Micu' lu' Bicu** › bifezi ce vrei › **Instalează**:
   - culorile și fonturile în Elementor (Setări site);
   - șabloanele „Antet” și „Subsol”, folosite pe tot site-ul;
   - pagina „Acasă (nou)”, salvată ca ciornă. O verifici, apoi o publici și o setezi ca primă pagină (bifa a doua sau Setări › Citire);
   - categoria „Pachete” și eticheta „Cel mai comandat”.
4. Pozele produselor: în WooCommerce › Produse. Pozele secțiunilor: în Elementor, pe fiecare widget.
5. Aspect › Personalizează › **Date restaurant (Google)**: programul și telefonul pentru rezultatele Google.

Nimic nu se schimbă pe site doar prin activarea temei, în afară de aspect: pașii de mai sus se rulează doar când apeși „Instalează”.

## Ce mai trebuie completat

Locurile marcate cu `[...]`: codul promoțional, pragul de livrare, nota și recenziile Google, programul locațiilor, telefonul de la Piața Vidin, datele firmei, linkurile App Store / Google Play, pictogramele ANPC SAL și SOL (imaginile oficiale de pe anpc.ro).

## Viteză

- fonturile sunt găzduite în temă (fără Google Fonts, fără cereri externe);
- un singur fișier CSS și un fișier JS mic, fără jQuery;
- pozele se încarcă leneș (lazy), cu excepția celei din prima secțiune;
- recomandat în Elementor › Setări › Funcții: „Optimized DOM Output”, „Improved Asset Loading”, „Inline Font Icons” active;
- recomandat pe server: cache de pagină (RunCloud Hub) care exclude coșul, finalizarea comenzii și contul.

## Dezvoltare

```bash
php wordpress/mlb-theme/tests/render-test.php            # randează fiecare widget și șablon, fără WordPress
php wordpress/mlb-theme/tests/preview.php /tmp/tema.html  # pagina de start, ca fișier HTML static
```

Testele folosesc înlocuitori minimali pentru WordPress, WooCommerce și Elementor (`tests/stubs.php`). Nu înlocuiesc o verificare pe un WordPress real cu Elementor.

Fonturile Barlow sunt sub licența SIL Open Font License 1.1 (`assets/fonts/OFL.txt`).
