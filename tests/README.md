# Dashboard state tests

Run `php tests/state.php`. No dependencies or web server are required. Tests use
temporary copies of the API and config; they do not modify your `urls.json`.

Coverage includes missing, empty, malformed and structurally invalid state,
restoration of custom URLs/options from a validated backup, corrupt/missing
backups, backup seeding on existing installations, byte-for-byte corruption
archives, interrupted temporary files, repeated reads, POST recovery, updates,
encoding failure without data loss, and empty URL lists.

Writes use a same-directory temporary file, flush it (and `fsync` when available),
then rename it over `urls.json`. This avoids truncating the live file during an
update. Before replacing valid primary state, its previous version is saved
atomically to `urls.json.bak`. Initialization seeds both copies; reading a valid
existing installation also creates or repairs a missing/corrupt backup.

If the primary is missing or invalid, the backup is validated and restored,
including user URLs and options. Defaults from `INIT_URL_PATH` are used only
when neither copy is valid. Damaged files are preserved as `*.corrupt-*` before
replacement. Recovery never copies damaged primary data over a good backup.

This does not guarantee survival of every power failure or serialize concurrent
read-modify-write requests. Restoring the previous version can lose the most
recent edit; failure of the SD card can damage both copies. I/O failures are
reported rather than silently accepted.
