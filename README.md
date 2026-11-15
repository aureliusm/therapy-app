# Sebi naproti — zapiski srečanj

Lokalna NativePHP desktop aplikacija za osebne terapijske zapiske.

## Zagon (desktop)

```bash
composer native:dev
```

ali:

```bash
npm run dev
php artisan native:run
```

## Mac build

```bash
php artisan native:build mac arm64
```

Ikona: `public/icon.png` (+ `public/icon.icns`).

Build izhod:
- `nativephp/electron/dist/mac-arm64/Sebi naproti.app`
- `nativephp/electron/dist/Sebi naproti-1.0.0-arm64.dmg`

## Posodobitve (updater)

Aplikacija v Nastavitvah preveri `UPDATE_MANIFEST_URL` (JSON) in prenese nov DMG.

### Objava nove različice

1. Zgradi in pripravi paket:

```bash
php artisan app:prepare-update --build --set-version=1.0.1 --notes="Popravki in izboljšave"
```

2. Naloži vsebino mape `releases/` na javni URL (npr. Cloudflare R2, S3, statični hosting).

3. V `.env` nastavi:

```
UPDATE_PUBLIC_BASE_URL=https://tvoj-host/sebi-updates
UPDATE_MANIFEST_URL=https://tvoj-host/sebi-updates/update.json
```

4. Ponovno zaženi `php artisan app:prepare-update` (brez `--build`), da v `update.json` zapiše prave URL-je.

5. Zgradi še enkrat produkcijski build z istim `UPDATE_MANIFEST_URL`, da bodo obstoječe instalacije znale preverjati posodobitve.

### GitHub Releases (opcijsko)

```
GITHUB_OWNER=...
GITHUB_REPO=...
GITHUB_TOKEN=...
```

Če je nastavljen GitHub in ni `UPDATE_MANIFEST_URL`, updater bere zadnji release.

> Elektronski NativePHP auto-updater (`NATIVEPHP_UPDATER_ENABLED=true`) na Macu deluje samo za **podpisane** aplikacije.

## Gemini

V `.env` nastavi:

```
GEMINI_API_KEY=tvoj_ključ
```

Queue worker za AI teče samodejno z NativePHP appom.
