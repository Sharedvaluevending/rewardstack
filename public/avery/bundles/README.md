# Avery `.avery` bundles (hosted)

These files must be **publicly accessible** so Avery Design & Print Online (DPO) can load them.

## What goes here

Place one `.avery` bundle per supported sticker size:

- `square-1in.avery`
- `square-1_5in.avery`
- `square-2in.avery`
- `square-4in.avery`

## Required merge field mapping

Each bundle must have a single merge field mapped to the column header configured in:

- `config/printkits.php` → `merge_column` (default: `qr_url`)

RewardStack will POST CSV merge data where each row contains a `qr_url` value like:

- `https://revenueqr.com/s/{qr_code}`

## Notes

- Do **not** commit secrets into these bundles.
- If you change filenames, update `config/printkits.php` accordingly.

