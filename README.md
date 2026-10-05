# Micu' lu' Bicu

Aplicația mobilă de comenzi a restaurantului Micu' lu' Bicu (Android + iOS) și backend-ul ei din WordPress.
Contextul complet al proiectului e în [CLAUDE.md](CLAUDE.md).

## Structură

```
app/                          Aplicația Expo (React Native, TypeScript)
wordpress/mlb-app-api/        Plugin WordPress: API-ul aplicației (/wp-json/mlb/v1)
wordpress/grandchef-campuri/  Plugin WordPress: câmpurile GrandChef pe produse (de adăugat sursa)
```

Fluxul unei comenzi: aplicația → `mlb-app-api` → comandă WooCommerce în `processing` → plugin-ul GrandChef existent → serverul GrandChef al locației.
Aplicația nu vorbește niciodată direct cu GrandChef.

## Secrete

Parolele GrandChef, cheile și tokenii stau doar în `.env`, în opțiunile WordPress sau în EAS secrets. Nu intră niciodată în repo.
