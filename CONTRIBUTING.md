# Contributing

Resurrection is a finished showcase project. `1.0.0`, its first release, came out on 2026-11-21. Since then it takes
bug reports and bug fixes, and no new gameplay, art or features (Jim Lunsford's decision of 2026-11-13). The plan and
where it stands are in [ROADMAP.md](ROADMAP.md).

## Report a bug

Open an [issue](https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection/issues) and say:

- the version: the footer of every page shows it ("Version: Resurrection ...");
- your PHP and database versions, and whether you play locally or on a server;
- what you did, what happened, and what you expected.

Check two lists first: the known issues in the current release notes (the first documents under
[Start here](docs/README.md#start-here)), and the deliberate [changes from 1.1.2](docs/CHANGES-FROM-1.1.2.md).

Never post a password, a configuration file, real player data or exploit details in an issue. A security problem goes
through [SECURITY.md](SECURITY.md), privately.

## Fix a bug

Send a pull request against `main` that fixes one bug and nothing else. It needs:

- **A test** that fails without the fix and passes with it: PHPUnit in `tests/unit/`, or a tooling test in
  `tests/tooling/` when it needs the game's web server. Never remove, skip or weaken a test to get a pass.
- **A record** in [docs/CHANGES-FROM-1.1.2.md](docs/CHANGES-FROM-1.1.2.md) when a player, an administrator or a host
  can notice the change: 1.1.2's behavior (read it at the tag `historical-source-1.1.2`), the new behavior and why.
- **An upgrade step** in `upgrades/` when the fix changes the installed schema or rows, so that an upgraded database
  ends up the same as a fresh install ([upgrades/README.md](upgrades/README.md)).
- **The living documents kept true**: a change to what a guide or reference describes updates it in the same commit
  ([docs/README.md](docs/README.md) lists them). A change to a hook or a helper a module uses updates
  [docs/MODULE-AUTHOR-GUIDE.md](docs/MODULE-AUTHOR-GUIDE.md).

The project's own rules for changes to this repository, which a fix keeps:

- `LICENSE.txt` and `README.txt` stay byte for byte as 1.1.2 shipped them, and the tag `historical-source-1.1.2`
  never moves.
- The original authors' copyright line, the license block and the Resurrection line under them stay in the template's
  footer, with the template check and the built-in frame that show them ([NOTICE](NOTICE) says how the software
  behaves).
- The game loads no Composer package when it runs, and gets no new dependency.
- Nothing from the module archive, and no other fork's code or art, is copied in.

## Run the checks

With PHP 8.4 or 8.5, Composer 2 and Python 3:

```sh
composer install
python3 scripts/verify-historical-baseline.py
python3 scripts/validate-project.py
composer lint
composer analyse
vendor/bin/phpstan analyse -c phpstan-new.neon
```

PHPUnit (`composer test`) and the web tests need an empty disposable database, named in `RESURRECTION_TEST_DB_HOST`,
`RESURRECTION_TEST_DB_USER`, `RESURRECTION_TEST_DB_PASSWORD` and `RESURRECTION_TEST_DB_NAME`, and PHP's
`sendmail_path` set to `/bin/false`: tests never send mail. Run the web test shard that holds your test with
`python3 scripts/tooling-shards.py --shards 5 --shard N` (`--shards 5` alone lists the shards).

CI runs on every pull request: Modern core on PHP 8.4 with MariaDB 11.4 and on PHP 8.5 with MySQL 8.4 (Composer's
checks, lint, both PHPStan gates, the release upgrade check, PHPUnit and the web tests in five shards), and Baseline
integrity. A fix is accepted on CI, with every job green; a local run is supporting evidence.

## How the project is run

Jim Lunsford decides what is done. Claude (Anthropic) writes the code from his briefs, in sessions that follow
[CLAUDE.md](CLAUDE.md), the project's standing rules; those rules hold for every change, whoever makes it.
