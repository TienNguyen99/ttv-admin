# Google Sheet sync

## Scope

Phase 6 applies the shared sync lifecycle to these read-only Google sources:

- `LENH_SAN_XUAT` -> `internal_production_orders`
- `DANH MUC` -> `internal_item_catalogs`

Google Sheet is only read by these sync endpoints. All imported data, run state and errors are written to the `internal` database. No TSoft table is written.

## Lifecycle

1. Acquire `internal-google-sync:{source}` cache lock.
2. Create an `internal_google_sync_runs` row with `running` status.
3. Read and parse the CSV source.
4. Compare each row's SHA-256 `source_hash` with the stored row.
5. Skip unchanged rows and persist only changed rows.
6. Record recoverable row errors in `internal_google_sync_errors` and continue.
7. Finish the run as `success`, `partial` or `failed` and release the lock.

A retry uses the same endpoint. Successful rows become `unchanged`; failed rows are attempted again, so retrying does not duplicate imported records.

## Endpoints

- `POST /api/lenh-san-xuat-sheet/dong-bo`
- `POST /api/danh-muc-noi-bo/dong-bo`
- `GET /api/dong-bo-google/lich-su?source=catalog&limit=20`
- `GET /api/dong-bo-google/lich-su/{run_id}`

HTTP `409` means another sync of the same source is running. A successful HTTP response can still have `data.failed > 0`; in that case the run status is `partial` and its row errors are available from the detail endpoint.

## Maintenance rules

- Keep source-specific parsing in its current controller.
- Keep locking, run state and error persistence in `InternalGoogleSyncCoordinator`.
- Call `InternalGoogleSyncContext::checkpoint()` for every source row.
- Catch only recoverable row-level failures inside the row loop. Let source, archive and reconciliation failures abort the run.
- Do not add Google or TSoft writes to this coordinator.
