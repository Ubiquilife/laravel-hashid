# CLAUDE.md — laravel-hashid

## Overview

Ubiquilife fork of ElfSundae's laravel-hashid. Generates reversible, non-sequential, URL-safe identifiers to obfuscate database IDs. Supports multiple encoding drivers: Base62, Base64, Hashids, Hex, and Optimus.

## Namespace

`ElfSundae\Laravel\Hashid`

## Key Classes

- **`HashidManager`** — Extends Laravel's `Manager`. Resolves the configured driver and delegates encoding/decoding.
- **`HashidServiceProvider`** — Auto-discovered. Registers the manager and `Hashid` facade.
- **`Facades\Hashid`** — Facade for `HashidManager`. Usage: `Hashid::encode($id)` / `Hashid::decode($hash)`.
- **Driver classes** — `Base62Driver`, `Base64Driver`, `HashidsDriver`, `HexDriver`, `OptimusDriver` (and Integer variants).
- **`helpers.php`** — Global `hashid_encode()` / `hashid_decode()` helper functions.

## Configuration

Driver and connection settings in `config/hashid.php`. Published via service provider.

## Testing

```bash
cd laravel-hashid && vendor/bin/phpunit
```

## Mandatory Rules

- This is a PRIVATE Ubiquilife package. Changes affect ALL apps.
- NEVER change the encode/decode output format — existing hashed URLs in production would break.
- NEVER change the driver interface without checking all consuming apps.
- Use British spelling in all text and comments.
- Test before committing.
- One logical change per commit.
