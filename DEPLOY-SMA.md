# MODX Revolution deployment notes

## Baseline

Upstream release `v3.2.4-pl` at `c7c38028e81022285a2f2aab9fd076bb9e2d89f4`.

## Deploy, seed, verify, reset

```bash
./tester-env deploy
./tester-env seed
./tester-env verify
./tester-env reset
```

The CLI builds editable source with `php:8.2-apache-bookworm` and MySQL 8.0,
uses port `8096` by default, and accepts `IMAGE_TAG`, `RUN_ID`, and `PORT`.
`IMAGE_TAG` names the image; `RUN_ID` only scopes the Compose project and MySQL volume.

Manager credentials: `tester-admin` / `modx-17082005` at `/manager/`.

## Seed and verification

The deterministic seed creates the published `Operations Hub` container with
`Harbor Safety Briefing` and `Autumn Berth Schedule`, plus three media sort
fixtures under `assets/sort-fixtures/` (`alpha-dock-manifest.txt` oldest,
`zulu-berth-report.txt` newest, fixed 2024 mtimes). `verify` checks the site,
manager response, exactly those resource titles through MODX's API, and the
three fixture files with exact mtimes.

## Browser smoke evidence

Manager login succeeded for `tester-admin`; Operations Hub, Harbor Safety
Briefing, and Autumn Berth Schedule were visible. A resource content edit was
saved, reloaded, and restored through the manager UI.
