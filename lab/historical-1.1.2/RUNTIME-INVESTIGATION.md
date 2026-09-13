# Runtime investigation, 2026-09-13

**No compatible runtime combination has yet been selected by execution. No clean install or gameplay rendering succeeded because neither was run.**

## Observed workspace capabilities

Git and Python are available. PHP, Docker, Podman, runc and containerd were not available; no Docker socket was present. These are Work workspace observations, not VPS observations.

Two isolation probes failed:

- `unshare --user --map-root-user --net true`: could not write `/proc/self/uid_map`, Operation not permitted.
- `bwrap --unshare-all --ro-bind / / --proc /proc --dev /dev /usr/bin/true`: could not open `/proc/7/ns/ns`, No such file or directory.

The probes executed only `true` if isolation could start. They did not execute PHP, a database or LoGD. No attempt was made to evade these restrictions. The repository therefore contains an **untested container scaffold**, not a demonstrated reproducible historical runtime.

## Source-derived candidate selection

PHP 5.6.40 is the newest PHP 5 release candidate worth testing first: the core's default mysql extension and magic-quotes compatibility assumptions prevent unchanged PHP 7/8 execution. This is a hypothesis from source/API history, not a successful runtime test. The source contains earlier PHP assumptions that can still fail on PHP 5.6.

Use an official PHP 5.6 CLI image, resolve its actual immutable digest, verify its provenance and build the historical mysql/mysqli extensions without network access. The Dockerfile is an untested recipe. Old image availability, architecture compatibility and bundled build tools are all open checks. Do not substitute an unreviewed third-party legacy image just to obtain a green result.

For the database, first test a supported MariaDB release with its default modes. MariaDB 11.8/10.11 are candidate lines, not a selected pair. Record engine/version, `sql_mode`, server/client/table encodings, collation and timezone before the installer. If rejected defaults, zero dates or bundled `TYPE=` installers block progress, record the exact statement and error before trying an isolated, explicit lab configuration. If obsolete syntax cannot be configured compatibly, consider an older disposable DB reference, recording why newer candidates failed. No source patch is authorized to silently manufacture the baseline.

C11 in `lib/installer/installer_stage_6.php` concerns the pre-1.1.0 upgrade generated-config branch; it must not be asserted to have blocked a clean install without an observed error. Dag and Drinks module installers have historical `TYPE=` syntax. A successful core install must be distinguished from successful installation of every bundled module.

## Scaffold safety and open validation

Compose has no published ports, an internal-only network with IPv6 disabled, app loopback HTTP, no Docker socket, no host network, non-root users, dropped capabilities, no-new-privileges, read-only root filesystems and disposable tmpfs database/application state. Outbound URL wrappers are disabled; mail is discarded by a clearly identified lab-only fixture. Container network isolation must be verified before the first application request.

The exact image's database UID/GID, writable paths, initial database setup, extension build, resource limits and Docker internal-network behavior still need testing. The proposed UID 999 is an assumption to verify against the selected database image. Digest fields remain null to prevent accidental execution with guessed inputs. `lab.py` exports exact Git blobs and never runs the application.

A reviewed image digest only pins bytes. It does not make unsupported software safe for public hosting. Never run this lab on `vps1.phoenix233.com`.

## Next execution experiment

1. Obtain a disposable Docker-capable workspace, not the VPS.
2. Resolve and record candidate image digests. Test extension presence and DB initialization privately.
3. Verify no host ports, no external egress, private DB connectivity and discarded email before application requests.
4. Run installer stages with synthetic data and the 24 bundled modules only. Record stages/schema/seed counts and exact blockers; do not import the missing archive recommendations.
5. Only after successful installation, capture account/login/village and one deterministic economy transition. Expand journeys after this smallest state baseline is real.

No `historical-characterization` Actions workflow is included because the lab has not passed these checks.

References: [PHP 7 removed extensions](https://www.php.net/manual/en/migration70.removed-exts-sapis.php), [PHP unsupported branches](https://www.php.net/eol.php), [Docker internal networks](https://docs.docker.com/reference/compose-file/networks/#internal).
