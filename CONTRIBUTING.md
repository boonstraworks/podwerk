# Contributing to Podwerk

Hello 👋 and thanks for your interest in contributing to Podwerk, the
maintained continuation of Podcast Generator.
You can contribute by:

* Writing code
* Reporting and searching for bugs and security issues
* Translating
* Requesting features

## Write code

There are no formal guidelines, but please keep in mind:

* Atomic commits and pull requests
* Keep the diffs small
* Keep backwards compatibility in mind: existing Podcast Generator 3.2
  installations, configs and themes must keep working
* Keep string translation in mind: wrap text in `_()` and pass the software
  name in as `%s` (`$app_name`), never write it in a translatable string
* Run `tests/smoke.sh` against a test installation before opening a pull
  request

## Report and search for bugs and security issues

If you found a bug, please report it on GitHub and make it as easy as
possible to reproduce.
If you found a security issue, PLEASE DO NOT open an issue on GitHub.
Instead have a look at [SECURITY.md](SECURITY.md).

## Translate

Translations are the `.po` files in
`PodcastGenerator/components/locale/<language>/LC_MESSAGES/`. Edit one with
a tool like [Poedit](https://poedit.net) and open a pull request. For a new
language, start from `PodcastGenerator/components/locale/messages.pot`.

## Request features

Feature requests are welcome as GitHub issues 😃
