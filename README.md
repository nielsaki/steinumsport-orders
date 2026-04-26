# Steinum Sport — klæðir (skipan)

WordPress plugin fyri at taka ímóti tilkunnum um klæðir til kappróðrarbátar. Tilkunn
senda admin-teldupost, PDF-kvittan til kundans teldupost, og møguliga goymsla í
dátugrunninum. Skipanin er bygd í somu náð sum
[**afturgjald / afturgjalds-skipan**](https://github.com/nielsaki/afturgjalds-skipan):
eitt shortcode, **topp-meny** í WP, **Stillingar + Fráboðanir**, **lokal próving uttan
WordPress** (`php -S` + `tests/serve.php`), og **CLI testar** uttan PHPUnit.

## Innihald

```
steinum-sport-clothes/
├── steinum-sport-clothes.php          # entrypoint
├── includes/
│   ├── class-ssc-sanitizer.php
│   ├── class-ssc-email-builder.php
│   ├── class-ssc-pdf.php
│   ├── class-ssc-logger.php
│   ├── class-ssc-mail.php             # test mode + log
│   ├── class-ssc-store.php            # dátugrunnur ({$prefix}ssc_submissions)
│   ├── class-ssc-submission.php       # sendir + goymur
│   ├── class-ssc-form.php             # form + shortcode
│   ├── class-ssc-settings.php         # Stillingar-síða
│   ├── class-ssc-plugin.php           # hekk + meny
│   └── admin/
│       ├── class-ssc-admin-list-table.php
│       └── class-ssc-admin-submissions.php
├── assets/
│   └── css/
│       ├── frontend.css
│       └── admin.css
├── tests/
│   ├── bootstrap.php                  # færir stubbar + føst testdata
│   ├── wp-stubs.php                   # stubbaður WordPress + SQLite $wpdb
│   ├── test-cases.php
│   ├── run-tests.php                  #   php tests/run-tests.php
│   └── serve.php                      #   php -S … tests/serve.php
└── bin/
    ├── preview-serve.sh               # shortcut til serve.php
    ├── package.sh                     # bygg .zip
    ├── wp-start.sh / wp-stop.sh        # (val) WordPress + wp-now
    └── test.sh / generate-sample-pdf   # (om hon eru til staðar)
```

## Shortcode

- `[steinum_sport_clothes_form]` — ráðlagt
- `[ssc_form]` — stutt alias

## Admin-síður í WordPress

Tá pluginið er virkja, kemur topp-menyin **Steinum Sport** (ikon: tag) við:

- **Steinum Sport → Fráboðanir** — listi, søk, støða, strika, purge eldri enn X dagar, detalja.
- **Steinum Sport → Stillingar** — teldupostur, yvirskriftir, PDF, royndar-tilkunn, log-útstykki í próvingarhami.

(Í gamla dokumentasjón var **Settings** — nú er alt undir hesa menyini.)

## Hvussu dátan verða goymd

Hvør innsending skrivar eina raðfylgd í `{$prefix}ssc_submissions` (innihald, JSON, støða,
tíðarstemplar, o.l.). Tað krevst ikki, um tú ikki ynskir goymslu:

```php
add_filter( 'ssc_store_submission', '__return_false' );
```

## Próvingarham (lokalt / staging)

Set hetta í `wp-config.php` (sama hugskotsleið sum `DRF_*` í afturgjaldsskipanini):

```php
define( 'SSC_EMAIL_TEST_MODE', true );
define( 'SSC_EMAIL_TEST_TO',   'tín@epost.fo' ); // valfrítt — annars admin-teldupostur
define( 'SSC_EMAIL_DRY_RUN',   true );          // valfrítt — einki wp_mail, bert logg
define( 'SSC_EMAIL_LOG_FILE',  WP_CONTENT_DIR . '/ssc-mail.log' ); // valfrítt
```

Í test mode verða móttakarar broytt og ein bannari lagdur í teldupostin; í dry run
verður alt loggað, men einki reint sendt.

## Lokal próving í kaga (uttan WordPress) — eins og í afturgjald

Frá hesi **plugin-mappuni** (har `steinum-sport-clothes.php` liggur):

```bash
php -S localhost:9090 -t . tests/serve.php
```

ella:

```bash
bash bin/preview-serve.sh
```

Opna síðan **http://localhost:9090/**. Tá sæst:

- Vinstru: formurin (somu felt sum á síðu).
- Høgru: loggur við hvønn teldupost, sum burturvildi verða sendur (PDF lýsing í logginum).
- Niðanfyri: goymd tilkunning (SQLite, vanliga `/tmp/ssc-preview.sqlite` á macOS).

Tísnýggja er *dry run* + *test mode* tvingað, so **ongin teldupostur fer út** úr hesum serverinum.

Eitt annað, fullt WordPress-umhværvi: `bash bin/wp-start.sh` (krevur bara Node.js) — sí niðanfyri.

## Lokal WordPress (Local / MAMP / o.a.)

1. Koyr plugin mappuna inn í `wp-content/plugins/steinum-sport-clothes/`.
2. Virkja undir **Plugins**.
3. Stovna síðu við shortcode ella gagnýt **Fráboðanir** til at síggja innsendingar.
4. Set próvingar-konstantir í `wp-config.php` um tú ynskir.

## Automatiskar royningar (CLI, uttan WordPress)

```bash
cd steinum-sport-clothes
php tests/run-tests.php
```

Hetta koyrir 15+ testar (sanitizing, email, PDF, goymsla, pipeline). Fæð `0` som
útgongd tá alt er gott.

## WordPress-umhværvi við eini skipan (val)

```bash
bash bin/wp-start.sh    # opnar http://localhost:9090/ (admin / password)
```

Støðan brúkar `@wp-now/wp-now` ella, við `WP_USE_DOCKER=1`, `@wordpress/env`. Sí
`bin/wp-start.sh` fyri nær reglur.

## Bygging til uppglóðing (zip)

```bash
bash bin/package.sh     # skrivar steinum-sport-clothes.zip
```

## GitHub Actions (CI + gagnmóttøka deploy)

Uti í repo-rotin er `.github/workflows/deploy.yml` — hann koyrir `php -l` og
`php tests/run-tests.php` við kvørjum push, og kunnu uppglóða plugin yvir **FTPS**
um `FTP_*` secrets eru sett. Sama hugskot sum í
[afturgjalds-skipan](https://github.com/nielsaki/afturgjalds-skipan).

## Version

Sjá `SSC_VERSION` í `steinum-sport-clothes.php`.
