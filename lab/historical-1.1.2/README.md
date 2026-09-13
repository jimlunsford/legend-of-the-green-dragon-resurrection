# Private historical characterization lab

**Status: untested scaffold. Runtime execution is blocked in the foundation workspace. NOT APPROVED FOR PUBLIC GAME HOSTING.**

The source comes only from the annotated `historical-source-1.1.2` tag. No archive modules are added. [Runtime investigation](RUNTIME-INVESTIGATION.md) records observed blockers and candidate selection. [Characterization plan](../../docs/CHARACTERIZATION-PLAN.md) defines evidence requirements.

## Safe static preparation

From repository root:

```sh
python3 lab/historical-1.1.2/lab.py preflight
python3 lab/historical-1.1.2/lab.py prepare
```

Preflight returns exit 2 when a container prerequisite is unavailable. Source-only preparation does not execute PHP, start containers or generate credentials. It exports and rehashes all 417 historical files into ignored `.runtime/source`. It refuses to overwrite an existing runtime directory. Do not commit that directory.

## Future container experiment, not a verified runbook

First perform the image/isolation checks in RUNTIME-INVESTIGATION.md. The image fields in `runtime-lock.example.json` deliberately contain null. A reviewed lock must specify `image@sha256:<64 hex digits>` values. Store the working lock outside tracked files, or later commit a reviewed, credential-free lock with actual test evidence. To prepare a fresh experiment, run `lab.py prepare --lock /path/to/reviewed-lock.json` from a clean runtime directory. It generates synthetic disposable DB credentials in `.runtime/lab.env` with owner-only permissions; never display or upload this file.

The Compose scaffold can subsequently be exercised in that isolated workspace using `docker compose --env-file .runtime/lab.env up --build -d` from this directory. This command was **not executed during foundation**. Do not add `ports`, change to host networking, or run PHP on the host. Fetch reviewed images before runtime startup; extension compilation requests build networking `none`.

App HTTP listens at `127.0.0.1:8080` inside the app container only. A future PHP CLI HTTP harness must run inside that container and retain cookies only in tmpfs. There is intentionally no host browser URL. DB hostname is `db`, database/user `lotgd_fixture`; generated credentials are research-only. The installer may create dbconnect.php in the disposable `/srv/game` copy. It must never write to the baseline mount or repository.

Before invoking LoGD, verify image versions/extensions, DB readiness, no published ports and blocked external egress. The scaffold does not claim those checks passed. Do not print `docker compose config`, `env`, raw application logs, or unfiltered database rows into CI artifacts, as these can expose generated secrets or authentication material.

Destroy containers with `docker compose --env-file .runtime/lab.env down --volumes --remove-orphans` after an experiment. All DB/game state is tmpfs and is lost when containers are removed. Retain only reviewed allowlisted evidence, then remove the local ignored runtime directory. No lab is installed on a VPS and no automatic workflow starts it.

## Fixture boundaries

`lab.ini` disables outbound URL wrappers and sends email to `/bin/true`. These are lab safety fixtures, not original behavior and not future production defaults. If an installer tries a dead external license/community URL, preserve the failure and use a labeled local fixture only after review. Do not make the container Internet-reachable or pretend a stub response was a historical server response.

Randomness and wall clock are not controlled by this scaffold. Record nondeterministic results; do not invent golden combat values. No known vulnerability is a desired compatibility contract.
