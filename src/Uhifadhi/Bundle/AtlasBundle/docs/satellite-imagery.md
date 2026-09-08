# Choosing the satellite imagery

`config/packages/atlas.yaml`:

```yaml
atlas:
    satellite:
        provider: esri      # esri (default) · google · custom
```

## Contents

- [`esri` — the default, and keyless](#esri-the-default-and-keyless)
- [`google` — opted into by name](#google-opted-into-by-name)
- [`custom` — your own imagery](#custom-your-own-imagery)

## `esri` — the default, and keyless

Esri World Imagery. No key, no session, no account, nothing that can be refused. This is what a
host gets for free, and it is a deliberate change from the platform's Google-first past: every map
on every page used to open by asking Google's Map Tiles API for a session token, and for an
EEA-billed account Google answers

```
403 · "satellite tiles and 3D tiles are not available for your account and region"
```

so the whole product ran on the fallback while filling the console with refusals for an answer it
had already accepted. Defaulting to the source that works removes that entire class of noise.

## `google` — opted into by name

```yaml
atlas:
    satellite:
        provider: google
        google:
            api_key: '%env(default::UHIFADHI_GOOGLE_MAPS_API_KEY)%'
```

| Env var | Meaning |
|---|---|
| `UHIFADHI_GOOGLE_MAPS_API_KEY` | Google Maps API key with Map Tiles enabled. Unset is fine — the layer stays on Esri. |

The key is **public by nature**: it travels inside every tile URL, so restrict it by HTTP referrer
at Google exactly as you would a Maps JS key. It is also only ever emitted on a page whose provider
is `google` — an `esri` deployment's HTML contains no Google key at all, even if one is configured.

The layer starts on Esri and upgrades itself (tiles and attribution together) when the session
resolves. A refused session is *remembered*, so the 403 is earned once per hour per tab rather than
once per map per mount.

## `custom` — your own imagery

```yaml
atlas:
    satellite:
        provider: custom
        custom:
            url_template: 'https://tiles.example.org/imagery/{z}/{x}/{y}.jpg'
            attribution: 'Imagery © the national mapping agency'
```

Any XYZ/WMTS template. `url_template` is required and checked at compile time — a custom source
with no url is a blank map at 3am rather than an error at deploy time. `attribution` is optional
but strongly advised: a blank attribution control is how an imagery licence gets breached quietly,
so a placeholder credit is emitted if you leave it out.
