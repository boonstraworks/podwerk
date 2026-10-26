# Upgrade from an older version

Make a backup first. Then download the newer version and upload the content
of its `PodcastGenerator/` folder to your website. If your client asks to
overwrite a file, answer yes to all files. That is all.

## From Podcast Generator 3.2.9 to Podwerk 3.2.10

Podwerk 3.2.10 is a drop-in update for Podcast Generator 3.2.9. No manual
steps are needed after copying the files:

* `config.php` keeps all settings. The first request after the upgrade
  updates only the version number in it and is served as usual: no
  "Please refresh" page, nobody is logged out.
* All users and passwords keep working.
* Episodes, media files, covers, categories, the freebox text, custom feed
  tags and buttons stay where they are; the only content file in the
  package is the default cover `images/itunes_image.jpg`, identical to the
  one in 3.2.9.
* The feed stays at the same URL (`feed.xml`, `feed.php`) with the same
  items: GUIDs, enclosure URLs, dates and titles are unchanged, also after
  the feed is regenerated. Podcast apps see no new or changed episodes.
* The active theme stays active. Themes made for any 3.2.x release,
  including your own, are offered in the theme switcher. The new Podwerk
  theme is only used by new installations.

### Requirements

No new requirements:

* PHP 7.0 or newer (the 3.2.9 package needed PHP 7.1 or newer because of
  its bundled Guzzle), with the `gettext` and `simplexml` extensions.
* The `curl` extension stays optional, as in 3.2.9: it is needed for
  WebSub pings and Podcast Index notifications, which are skipped without
  it.
* No database, no new folders, no new write permissions.

### Files

* No file was removed or renamed outside `vendor/`. New files:
  `core/podcastindex.php`, `core/misc/html_sanitizer.php`,
  `themes/default/style/custom.css` and `themes/podwerk/`.
* These `vendor/` packages are no longer used: `guzzlehttp/guzzle`,
  `guzzlehttp/promises`, `guzzlehttp/psr7`, `podcastindex/podcastindex-php`,
  `psr/http-message`, `ralouphie/getallheaders`,
  `symfony/polyfill-intl-idn`, `symfony/polyfill-intl-normalizer` and
  `symfony/polyfill-php72`. Copying the new package over the old one
  replaces the Composer autoloader, so they are never loaded again. Deleting
  them is optional cleanup.
* The release zip has the same layout as the Podcast Generator zips:
  documentation in the root, the application in `PodcastGenerator/` with
  `vendor/` and the compiled translations included.

### How this is tested

`tests/upgrade.sh` installs the official `PodcastGenerator-3.2.9.zip`
(checksum pinned) and fills it through its own admin pages: two users, a
custom theme made for 3.2.9, a category, settings with quotes, a dollar
sign and accents, a freebox text and two episodes. It then copies the new
package over it without removing anything, the way auto-installers and FTP
upgrades do, and checks with all PHP errors, warnings and deprecations
shown:

* every setting in `config.php` except the version number is unchanged;
* both users can log in;
* the custom theme is still active and offered in the theme switcher;
* the home page, categories page, feed and every admin page load without
  PHP messages;
* `feed.xml` has the same items, GUIDs and enclosure URLs, also after the
  feed is regenerated;
* media files, covers, categories, freebox and theme files are untouched.

GitHub Actions runs it on PHP 7.1 to 8.5 for every push, together with
`tests/smoke.sh` on a fresh installation (PHP 7.0 to 8.5), and again on the
built zip before a release is published.

## From Podcast Generator 3.1 or 3.2.0 - 3.2.8

Upload the files the same way. The first visit afterwards converts the old
configuration and asks you to refresh the page once.
