# Hashid Standard — Ubiquilife Platform

**This configuration is LOCKED. Do not change the alphabet or min_length once any app is in production. Encoded IDs in URLs, APIs, and databases will break permanently.**

## Alphabet

```
23456789ABCDEFGHJKMPQRSTVWXYZ
```

29 characters. Uppercase + digits only.

### Excluded characters (5)

| Char | Reason |
|------|--------|
| I | Confused with 1 |
| L | Confused with 1 |
| N | Confused with M |
| O | Confused with 0 |
| U | Confused with V |

## Configuration

All apps use identical settings via `.env`:

```env
HASHID_CONNECTION=hashids_hex
HASHIDS_ALPHABET=23456789ABCDEFGHJKMPQRSTVWXYZ
HASHIDS_MIN_LENGTH=27
HASHIDS_SALT=<unique per app>
```

### Connection: `hashids_hex`

All QoModels use UUID primary keys. The `hashids_hex` driver encodes UUIDs via `encodeUuidForFrontend()` in `HasAppBaseAttributes` — a BigInteger base conversion (not the hashids library's encode/decode). Salt does not affect UUID encoding. Salt only applies to the rare non-UUID fallback path.

### Min length: 27

UUID base conversion naturally produces 27 chars with a 29-char alphabet (ceil(128 / log2(29)) = 27).

### Capacity

- **29^27 ≈ 3.05 × 10^39** possible hashid values
- **2^128 = 3.4 × 10^38** possible UUIDs
- Hashid space exceeds UUID space by **~9x** — no collisions possible

### Salt

Each app has a unique salt in `.env`. Since the primary UUID encoding path doesn't use salt, this only affects the hashids library fallback for non-UUID values. Different salts mean the same non-UUID value produces different hashids in different apps (by design).

## Config file

`config/hashid.php` — published in every app. All four hashids connections (`hashids`, `hashids_integer`, `hashids_hex`, `hashids_string`) read from the same env vars:

```php
'salt' => env('HASHIDS_SALT', ''),
'min_length' => env('HASHIDS_MIN_LENGTH', 27),
'alphabet' => env('HASHIDS_ALPHABET', '23456789ABCDEFGHJKMPQRSTVWXYZ'),
```

No `_HEX_`, `_STRING_`, or `_INTEGER_` prefixed env vars. One set of vars, all connections.

## Apps

All 16 apps are standardised:

test-app, identime, ulife, uwork, sitebase, upp, roadmap, support, ads, uec-manager, uforge, ulearn, usearch, uspace, uai, label-connect
