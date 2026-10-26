#!/usr/bin/env bash
# Upgrade test: Podcast Generator 3.2.9 with real content -> this checkout.
#
# Usage: tests/upgrade.sh [php-binary] [port]
#
# Installs the official Podcast Generator 3.2.9 package (the release zip,
# checksum pinned; OLD_ZIP may point to a local copy) in a temporary
# directory and fills it through its own admin pages: two users, a custom
# theme made for 3.2.9, a category, settings with quotes and accents, a
# freebox text and two episodes. Then the new package is copied over it the
# way Installatron and a manual FTP upgrade do: files are added and
# overwritten, nothing is removed, config.php and the content stay.
# The new package is NEW_ZIP (a built release zip) or else the release
# package of REF (default HEAD: git archive + composer install --no-dev).
#
# After the upgrade nothing may have changed for visitors, admins or
# subscribers: same settings and users, same theme, same media files, same
# feed URL with the same items (GUIDs, enclosure URLs), also after the feed
# is regenerated. Every page must load without PHP warnings or deprecations.
#
# Needs bash, git, curl, unzip, sha256sum, composer and the php CLI. Starts PHP's built-in web
# server on 127.0.0.1:<port> (default 8765).

set -u
PHP="${1:-php}"
PORT="${2:-8765}"
REF="${REF:-HEAD}"
REPO=$(cd "$(dirname "$0")/.." && pwd)
BASE="http://127.0.0.1:$PORT"
ADMIN_USER=admin
ADMIN_PASS='Upgrade-test-1'
SECOND_USER=jan
SECOND_PASS='Second-user-2'
# Double quotes, ampersand, dollar sign and an accent (UTF-8, percent-encoded so
# that no shell or code page touches it). No apostrophe: 3.2.9 writes it
# unescaped into config.php, which breaks the site before any upgrade.
TITLE='The%20%22Backyard%22%20%26%20co%3A%20%245%20caf%C3%A9'

WORK=$(mktemp -d)
SITE="$WORK/site/PodcastGenerator"
JAR="$WORK/cookies"
OUT="$WORK/body"
SERVER_PID=
cleanup() {
    [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null
    [ -n "${KEEP:-}" ] && echo "kept: $WORK" || rm -rf "$WORK"
}
trap cleanup EXIT
FAILS=0
# A path on this side becomes C:/... for a Windows php.exe or curl (Git Bash)
native() { if command -v cygpath >/dev/null; then cygpath -m "$1"; else echo "$1"; fi; }
# Some setups point COMPOSER at a directory; composer reads it as the file name
export COMPOSER=composer.json

ok() { echo "OK   $1"; }
fail() {
    echo "FAIL $1"
    FAILS=$((FAILS + 1))
}
die() {
    echo "FAIL $1"
    exit 1
}

fetch() {
    local url="$1"
    shift
    curl -s -b "$JAR" -c "$JAR" -o "$OUT" -w '%{http_code}' "$@" "$url"
}
php_errors() {
    grep -oE '(<b>|^)(Fatal error|Parse error|Warning|Deprecated|Notice)(</b>)?: [^<]*(<b>[^<]*</b>)?' "$OUT" | head -3
}
check_page() {
    local code
    code=$(fetch "$2")
    if [ "$code" = 200 ] && [ -z "$(php_errors)" ]; then
        ok "$1"
    else
        fail "$1 (HTTP $code)"
        php_errors
    fi
}
token() {
    grep -o 'name="token" value="[^"]*"' "$OUT" | head -1 | sed 's/.*value="//; s/"$//'
}
# post_form <page> <query> [curl args...]: posts to an admin page with a fresh token
post_form() {
    local page="$1" query="$2" tok
    shift 2
    fetch "$BASE/admin/$page" >/dev/null
    tok=$(token)
    fetch "$BASE/admin/$page?$query" --data-urlencode "token=$tok" "$@"
}
login() {
    rm -f "$JAR"
    fetch "$BASE/admin/login.php" >/dev/null
    fetch "$BASE/admin/login.php?login=1" --data-urlencode "username=$1" --data-urlencode "password=$2"
}

# start_server <php -d options...>
start_server() {
    "$PHP" "$@" -S "127.0.0.1:$PORT" -t "$(native "$SITE")" >"$WORK/server.log" 2>&1 &
    SERVER_PID=$!
    for _ in $(seq 50); do
        curl -s -o /dev/null "$BASE/favicon.ico" && return 0
        sleep 0.1
    done
    die "PHP web server did not start on port $PORT"
}
stop_server() {
    kill "$SERVER_PID" 2>/dev/null
    wait "$SERVER_PID" 2>/dev/null
    SERVER_PID=
}

# build <ref> <dir>: release package of <ref>, as it would be shipped
build() {
    mkdir -p "$2"
    git -C "$REPO" archive --format=tar "$1" PodcastGenerator | tar -x -C "$2" || die "git archive $1"
    (cd "$2/PodcastGenerator" && composer install --no-dev --no-interaction --quiet) || die "composer install for $1"
}

# Settings exactly as PHP reads them from config.php
config_values() {
    "$PHP" -r 'function c($f) { include $f; unset($f); return get_defined_vars(); }
        $v = c($argv[1]); ksort($v); echo json_encode($v, JSON_PRETTY_PRINT), "\n";' "$(native "$SITE/config.php")"
}
# Feed items as subscribers see them, one JSON object per line
feed_items() {
    "$PHP" -r '$x = simplexml_load_string(stream_get_contents(STDIN)); if (!$x) exit(1);
        foreach ($x->channel->item as $i) echo json_encode([(string) $i->title, (string) $i->guid,
            (string) $i->enclosure["url"], (string) $i->enclosure["length"], (string) $i->pubDate,
            (string) $i->category]), "\n";' <"$OUT"
}
content_hashes() {
    # The placeholder .gitignore files ship with every release and may change
    (cd "$SITE" && find media images categories.xml freebox-content.txt themes/mytheme -type f ! -name .gitignore -print0 \
        | sort -z | xargs -0 sha256sum)
}

# --- 1. Podcast Generator 3.2.9 with content -----------------------------
echo "Upgrade test on PHP $("$PHP" -r 'echo PHP_VERSION;')"
OLD_ZIP_URL=https://github.com/PodcastGenerator/PodcastGenerator/releases/download/v3.2.9/PodcastGenerator-3.2.9.zip
OLD_ZIP_SHA256=f2962195b51071ef910455f12b8aa96f30d42925b58f448d3e81cf25e5afb17a
if [ -z "${OLD_ZIP:-}" ]; then
    OLD_ZIP="$WORK/PodcastGenerator-3.2.9.zip"
    curl -sfL -o "$OLD_ZIP" "$OLD_ZIP_URL" || die "download $OLD_ZIP_URL"
fi
[ "$(sha256sum <"$OLD_ZIP" | cut -d' ' -f1)" = "$OLD_ZIP_SHA256" ] || die "checksum of the 3.2.9 zip"
mkdir -p "$WORK/site"
unzip -q "$OLD_ZIP" -d "$WORK/site" || die "unzip 3.2.9"
if [ -n "${NEW_ZIP:-}" ]; then
    echo "New package: $NEW_ZIP"
    mkdir -p "$WORK/release"
    unzip -q "$NEW_ZIP" -d "$WORK/release" || die "unzip $NEW_ZIP"
else
    echo "New package: release package of $REF"
    build "$REF" "$WORK/release"
fi

# A theme someone made for 3.2.9, with a marker to recognise it
cp -r "$SITE/themes/default" "$SITE/themes/mytheme"
sed -i 's/"name": *"[^"]*"/"name": "Upgrade test theme"/' "$SITE/themes/mytheme/theme.json"
echo '<!-- upgrade-test-theme -->' >>"$SITE/themes/mytheme/index.php"

# 3.2.9 prints deprecations on new PHP versions; keep them out of the pages
start_server -d display_errors=0
fetch "$BASE/setup/step1.php?done=1" -d 'lang=en_US' >/dev/null
fetch "$BASE/setup/step3.php?create=1" --data-urlencode "username=$ADMIN_USER" \
    --data-urlencode "password=$ADMIN_PASS" --data-urlencode "password2=$ADMIN_PASS" >/dev/null
[ -f "$SITE/config.php" ] || die "3.2.9 setup"
# On Windows 3.2.9 writes the backslashes of the path into a string that drops
# them when read back; use forward slashes, as on a Linux host
if command -v cygpath >/dev/null; then
    sed -i "s|^\(\$absoluteurl = \)'[^']*'|\1'$(native "$SITE")/'|" "$SITE/config.php"
fi
code=$(login "$ADMIN_USER" "$ADMIN_PASS"); [ "$code" = 302 ] || die "3.2.9 login (HTTP $code)"

post_form pg_users.php create=1 --data-urlencode "username=$SECOND_USER" \
    --data-urlencode "password=$SECOND_PASS" --data-urlencode "password2=$SECOND_PASS" >/dev/null
post_form podcast_details.php edit=1 -d "podcast_title=$TITLE" \
    --data-urlencode 'podcast_subtitle=Walks, birds & bugs' \
    --data-urlencode 'podcast_description=Every week a walk through the garden.' \
    --data-urlencode 'copyright=CC-BY 4.0' --data-urlencode 'author_name=Upgrade Test' \
    --data-urlencode 'author_email=upgrade@example.com' --data-urlencode 'feed_language=nl' >/dev/null
post_form episodes_manage_cats.php add=1 --data-urlencode 'categoryname=Nature walks' >/dev/null
post_form theme_change.php change=1 >/dev/null
post_form theme_freebox.php change=1 --data-urlencode 'content=<b>Welcome</b> to the garden' >/dev/null

MP3="$WORK/episode.mp3"
# 80 silent MPEG-1 Layer III frames (128 kbit/s, 44.1 kHz): about 2 seconds
for _ in $(seq 80); do
    printf '\xff\xfb\x90\x00'
    head -c 413 /dev/zero
done >"$MP3"
# upload <title> <date> [curl args...]
upload() {
    fetch "$BASE/admin/episodes_upload.php" >/dev/null
    local tok
    tok=$(token)
    fetch "$BASE/admin/episodes_upload.php" --form-string "token=$tok" \
        --form-string "title=$1" --form-string 'shortdesc=Short description' \
        --form-string 'longdesc=Long description' --form-string "date=$2" \
        --form-string 'time=09:30' --form-string 'explicit=no' --form-string 'episodenum=' \
        --form-string 'seasonnum=' --form-string 'itunesKeywords=garden' \
        --form-string 'authorname=' --form-string 'authoremail=' "${@:3}" \
        -F "file=@$(native "$MP3");type=audio/mpeg" >/dev/null
}
upload 'Robins & "wrens"' 2026-09-01 --form-string 'category[]=nature_walks'
upload 'Night moths' 2026-09-08
[ "$(find "$SITE/media" -name '*.mp3' | wc -l)" = 2 ] || die "3.2.9 episode upload"

fetch "$BASE/feed.xml" >/dev/null
feed_items >"$WORK/feed-before" || die "3.2.9 feed.xml"
[ "$(wc -l <"$WORK/feed-before")" = 2 ] || die "3.2.9 feed.xml has no 2 episodes"
grep -q '<category1PG>nature_walks<' "$SITE"/media/2026-09-01_*.xml || die "3.2.9 episode category"
config_values >"$WORK/config-before" || die "3.2.9 config.php"
content_hashes >"$WORK/hashes-before"
stop_server
ok "3.2.9 installed with 2 users, custom theme, category, freebox and 2 episodes"

# --- 2. upgrade: copy the release package over it -------------------------
cp -r "$WORK/release/PodcastGenerator/." "$SITE/"
[ -f "$SITE/core/podcastindex.php" ] || die "release package not copied"
ok "release package copied over the installation"

# --- 3. after the upgrade: nothing changed --------------------------------
start_server -d display_errors=1 -d error_reporting=-1

check_page "home page" "$BASE/index.php"
if grep -q 'upgrade-test-theme' "$OUT"; then ok "custom 3.2.9 theme still active"; else fail "custom theme not active"; fi
if grep -q 'Robins' "$OUT" && grep -q 'Night moths' "$OUT"; then ok "both episodes on the home page"; else fail "episodes missing on the home page"; fi
check_page "categories page" "$BASE/categories.php"

config_values >"$WORK/config-after" || fail "config.php unreadable after upgrade"
if "$PHP" -r '$a = json_decode(file_get_contents($argv[1]), true); $b = json_decode(file_get_contents($argv[2]), true);
    $bad = 0; foreach ($a as $k => $v) if ($k != "podcastgen_version" && (!array_key_exists($k, $b) || $b[$k] !== $v)) { echo "  changed: $k\n"; $bad++; }
    exit($bad ? 1 : 0);' "$(native "$WORK/config-before")" "$(native "$WORK/config-after")"; then
    ok "all other $(($(grep -c '^    "' "$WORK/config-before") - 1)) settings unchanged (title, users, theme, feed language...)"
else
    fail "settings changed by the upgrade"
fi
NEW_VERSION=$(grep -o '"podcastgen_version": "[^"]*' "$WORK/config-after" | cut -d'"' -f4)
if [ "$NEW_VERSION" != 3.2.9 ]; then ok "version number updated to $NEW_VERSION"; else fail "version number not updated"; fi

code=$(fetch "$BASE/feed.xml")
if [ "$code" = 200 ] && feed_items | cmp -s - "$WORK/feed-before"; then
    ok "feed.xml: same URL, same items, GUIDs and enclosure URLs"
else
    fail "feed.xml changed (HTTP $code)"
fi
check_page "feed.php" "$BASE/feed.php"

if [ "$(login "$SECOND_USER" "$SECOND_PASS")" = 302 ]; then ok "second user can log in"; else fail "second user cannot log in"; fi
if [ "$(login "$ADMIN_USER" "$ADMIN_PASS")" = 302 ]; then ok "admin can log in"; else die "admin cannot log in"; fi
for page in index episodes_upload episodes_list episodes_bulk episodes_ftp_feature \
    episodes_manage_cats podcast_details store_cat store_cover theme_change theme_freebox \
    theme_buttons pg_config pg_users pg_integrations; do
    check_page "admin/$page.php" "$BASE/admin/$page.php"
done
fetch "$BASE/admin/theme_change.php" >/dev/null
if grep -q 'Upgrade test theme' "$OUT"; then ok "3.2.9 theme offered in the theme switcher"; else fail "3.2.9 theme not offered"; fi
fetch "$BASE/admin/episodes_list.php" >/dev/null
if grep -q 'Robins' "$OUT" && grep -q 'Night moths' "$OUT"; then ok "both episodes in the episode list"; else fail "episodes missing in the episode list"; fi

# Regenerating the feed (also done by every upload) must keep the items
check_page "admin/episodes_regenerate.php" "$BASE/admin/episodes_regenerate.php"
fetch "$BASE/feed.xml" >/dev/null
if feed_items | cmp -s - "$WORK/feed-before"; then
    ok "regenerated feed keeps the same items, GUIDs and enclosure URLs"
else
    fail "regenerated feed differs"
    feed_items | diff "$WORK/feed-before" - | head -6
fi
if grep -q '<generator>' "$OUT"; then ok "feed generator: $(grep -o '<generator>[^<]*' "$OUT" | cut -c12-)"; fi

if content_hashes | cmp -s - "$WORK/hashes-before"; then
    ok "media, cover, categories, freebox and theme files untouched"
else
    fail "content files changed"
    content_hashes | diff "$WORK/hashes-before" - | head -6
fi

echo
if [ "$FAILS" -eq 0 ]; then
    echo "ALL OK"
else
    echo "$FAILS FAILED"
fi
exit "$FAILS"
