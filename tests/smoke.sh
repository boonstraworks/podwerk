#!/usr/bin/env bash
# Smoke test for a running Podwerk (or Podcast Generator 3.2) installation.
#
# Usage: tests/smoke.sh <base-url> <admin-user> <admin-password>
#
# <base-url> points at the PodcastGenerator directory, for example
# http://localhost/podwerk/PodcastGenerator. On a fresh copy (no
# config.php yet) the setup is run first with the given credentials.
# Run it against a throwaway installation: it changes settings and uploads
# an episode (media/<date>_smoke.mp3). PHP should show all errors (error_reporting = -1), so that
# warnings and deprecations end up in the pages and fail the test.
#
# Needs bash, curl and the php CLI.

set -u
BASE="${1%/}"
ADMIN_USER="$2"
ADMIN_PASS="$3"

WORK=$(mktemp -d)
JAR="$WORK/cookies"
OUT="$WORK/body"
trap 'rm -rf "$WORK"' EXIT
FAILS=0

ok() { echo "OK   $1"; }
fail() {
    echo "FAIL $1"
    FAILS=$((FAILS + 1))
}

# fetch <url> [curl args...]: body goes to $OUT, prints the HTTP status
fetch() {
    local url="$1"
    shift
    curl -s -b "$JAR" -c "$JAR" -o "$OUT" -w '%{http_code}' "$@" "$url"
}

php_errors() {
    grep -oE '(<b>|^)(Fatal error|Parse error|Warning|Deprecated|Notice)(</b>)?: [^<]*(<b>[^<]*</b>)?' "$OUT" | head -3
}

# check_page <label> <url>: expects HTTP 200 and no PHP errors in the body
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

# --- setup -----------------------------------------------------------------
if curl -s -o /dev/null -w '%{redirect_url}' "$BASE/index.php" | grep -q 'setup'; then
    fetch "$BASE/setup/step1.php?done=1" -d 'lang=en_US' >/dev/null
    code=$(fetch "$BASE/setup/step3.php?create=1" \
        --data-urlencode "username=$ADMIN_USER" \
        --data-urlencode "password=$ADMIN_PASS" \
        --data-urlencode "password2=$ADMIN_PASS")
    if [ "$code" = 302 ] && [ -z "$(php_errors)" ]; then ok "setup"; else fail "setup (HTTP $code)"; php_errors; fi
fi

# --- public pages ----------------------------------------------------------
check_page "home page" "$BASE/index.php"
check_page "feed.php" "$BASE/feed.php"
check_page "categories" "$BASE/categories.php"
check_page "admin/forgot.php" "$BASE/admin/forgot.php"
# Reflected XSS: an XHTML script element injected into the XML feed
fetch "$BASE/feed.php?cat=x%22/%3E%3Cscript%20xmlns=%22http://www.w3.org/1999/xhtml%22%3Ealert(1)%3C/script%3E" >/dev/null
if grep -q '<script' "$OUT"; then fail "reflected XSS via feed.php?cat"; else ok "feed.php?cat escaped"; fi
code=$(fetch "$BASE/config.php")
if [ "$code" = 200 ] && [ ! -s "$OUT" ]; then ok "config.php prints nothing"; else fail "config.php output (HTTP $code)"; fi

# --- login -----------------------------------------------------------------
fetch "$BASE/admin/login.php" >/dev/null
code=$(fetch "$BASE/admin/login.php?login=1" \
    --data-urlencode "username=$ADMIN_USER" --data-urlencode "password=$ADMIN_PASS")
if [ "$code" = 302 ]; then ok "login"; else fail "login (HTTP $code)"; exit 1; fi

# --- every admin page ------------------------------------------------------
for page in index episodes_upload episodes_list episodes_bulk episodes_ftp_feature \
    episodes_manage_cats episodes_regenerate podcast_details store_cat store_cover \
    theme_change theme_freebox theme_buttons pg_config pg_users pg_integrations; do
    check_page "admin/$page.php" "$BASE/admin/$page.php"
done

# --- CSRF: settings forms must reject requests without the token ----------
for target in "pg_integrations.php?edit=1" "pg_config.php?edit=1" "podcast_details.php?edit=1"; do
    fetch "$BASE/admin/$target" --data-urlencode 'websub_server=https://evil.example/' \
        --data-urlencode 'podcast_title=csrf' >/dev/null
    if grep -q 'Potential CSRF attack' "$OUT"; then ok "CSRF blocked: $target"; else fail "CSRF not blocked: $target"; fi
done

# --- user management: users_json must stay valid --------------------------
# login_as <user> <password>: logs in with a separate cookie jar, prints the HTTP status
login_as() {
    local jar="$WORK/cookies-user"
    rm -f "$jar"
    curl -s -b "$jar" -c "$jar" -o /dev/null "$BASE/admin/login.php"
    curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' \
        --data-urlencode "username=$1" --data-urlencode "password=$2" "$BASE/admin/login.php?login=1"
}
# users_action <query> [curl args...]: posts to pg_users.php with a fresh token
users_action() {
    local query="$1"
    shift
    fetch "$BASE/admin/pg_users.php" >/dev/null
    local tok
    tok=$(token)
    fetch "$BASE/admin/pg_users.php?$query" --data-urlencode "token=$tok" "$@" >/dev/null
}
users_action "create=1" --data-urlencode "username=smoke'user" \
    --data-urlencode 'password=first-pass' --data-urlencode 'password2=first-pass'
if [ "$(login_as "smoke'user" first-pass)" = 302 ]; then ok "new user can log in"; else fail "new user cannot log in"; fi
users_action "change=smoke%27user" --data-urlencode 'password=second-pass' --data-urlencode 'password2=second-pass'
if [ "$(login_as "smoke'user" second-pass)" = 302 ]; then ok "changed password works"; else fail "changed password does not work"; fi
users_action "change=smoke%27user" --data-urlencode 'password=' --data-urlencode 'password2='
if [ "$(login_as "smoke'user" second-pass)" = 302 ]; then ok "empty password rejected"; else fail "empty password locked the user out"; fi
users_action "delete=smoke%27user"
if [ "$(login_as "smoke'user" second-pass)" != 302 ]; then ok "deleted user cannot log in"; else fail "deleted user can still log in"; fi
if [ "$(login_as "$ADMIN_USER" "$ADMIN_PASS")" = 302 ]; then ok "admin login after user changes"; else fail "admin login broken after user changes"; exit 1; fi

# --- settings with quotes, backslashes and code ----------------------------
# save_details <title> <subtitle> <description> [extra curl args...]
save_details() {
    fetch "$BASE/admin/podcast_details.php" >/dev/null
    local tok
    tok=$(token)
    fetch "$BASE/admin/podcast_details.php?edit=1" \
        --data-urlencode "token=$tok" \
        --data-urlencode "podcast_title=$1" \
        --data-urlencode "podcast_subtitle=$2" \
        --data-urlencode "podcast_description=$3" \
        --data-urlencode 'copyright=CC-BY' \
        --data-urlencode 'author_name=Smoke Test' \
        --data-urlencode 'author_email=smoke@example.com' \
        --data-urlencode 'feed_language=en' "${@:4}" >/dev/null
}
# 1. a value that closes the PHP string and appends code
save_details "Smoke test" "x'; echo 'PWNED'; //" "Description"
sleep 3 # OPcache may serve the previous config.php for up to 2 seconds
code=$(fetch "$BASE/config.php")
if [ "$code" = 200 ] && ! grep -q PWNED "$OUT"; then ok "no code injection via settings"; else fail "config.php runs injected code"; fi
# 2. a line break followed by another config line, and a protected key
save_details "Smoke test" "Subtitle" "line one
\$users_json = 'hacked';" --data-urlencode 'users_json=hacked'
# 3. quotes, ampersands and semicolons in a normal value
TITLE="Steven's \"test\" podcast; AC/DC & co"
save_details "$TITLE" "Subtitle" "Description"
fetch "$BASE/admin/podcast_details.php" >/dev/null
if grep -qF "value=\"Steven&#039;s &quot;test&quot; podcast; AC/DC &amp; co\"" "$OUT"; then
    ok "title with quotes saved intact"
else
    fail "title with quotes not saved intact"
fi
# 4. stored XSS through the podcast title (CVE-2023-53920)
save_details '<img src=x onerror=alert(53920)>' "Subtitle" "Description"
fetch "$BASE/index.php" >/dev/null
if grep -q '<img src=x onerror=alert(53920)>' "$OUT"; then fail "podcast title XSS"; else ok "podcast title escaped on home page"; fi
save_details "$TITLE" "Subtitle" "Description"

# 5. stored XSS through the freebox (CVE-2023-53919)
fetch "$BASE/admin/theme_freebox.php" >/dev/null
TOKEN=$(token)
fetch "$BASE/admin/theme_freebox.php?change=1" --data-urlencode "token=$TOKEN" \
    --data-urlencode 'content=<b>freebox ok</b><script>alert(53919)</script><a href="javascript:alert(1)">x</a>' >/dev/null
fetch "$BASE/index.php" >/dev/null
if grep -q '<b>freebox ok</b>' "$OUT" && ! grep -q 'alert(53919)\|javascript:alert' "$OUT"; then
    ok "freebox keeps formatting, drops scripts"
else
    fail "freebox XSS"
fi

# The protected users_json key must not change: logging in again still works
rm -f "$JAR"
fetch "$BASE/admin/login.php" >/dev/null
code=$(fetch "$BASE/admin/login.php?login=1" \
    --data-urlencode "username=$ADMIN_USER" --data-urlencode "password=$ADMIN_PASS")
if [ "$code" = 302 ]; then ok "login still works after settings change"; else fail "login broken after settings change"; exit 1; fi

# --- upload an episode -----------------------------------------------------
MP3="$WORK/smoke.mp3"
# 80 silent MPEG-1 Layer III frames (128 kbit/s, 44.1 kHz): about 2 seconds
for _ in $(seq 80); do
    printf '\xff\xfb\x90\x00'
    head -c 413 /dev/zero
done >"$MP3"
# Git Bash on Windows does not translate the path inside -F "file=@..."
MP3_ARG="$MP3"
if command -v cygpath >/dev/null; then MP3_ARG=$(cygpath -m "$MP3"); fi
fetch "$BASE/admin/episodes_upload.php" >/dev/null
TOKEN=$(token)
# --form-string sends text fields as-is (-F would interpret <, @ and quotes)
code=$(fetch "$BASE/admin/episodes_upload.php" \
    --form-string "token=$TOKEN" --form-string "title=Smoke <episode> & \"quotes\"" \
    --form-string 'shortdesc=Short ]]> desc <script>alert(4)</script>' \
    --form-string 'longdesc=Long description' \
    --form-string "date=$(date +%Y-%m-%d)" --form-string 'time=12:00' \
    --form-string 'explicit=no</explicitPG><x>' --form-string 'episodenum=1' \
    --form-string 'seasonnum=1' --form-string 'itunesKeywords=smoke' \
    --form-string 'authorname=' --form-string 'authoremail=' \
    -F "file=@$MP3_ARG;type=audio/mpeg")
if { [ "$code" = 200 ] || [ "$code" = 302 ]; } && [ -z "$(php_errors)" ] \
    && ! grep -qi 'invalid\|unsupported\|error' "$OUT"; then
    ok "episode upload (HTTP $code)"
else
    fail "episode upload (HTTP $code)"
    php_errors
fi
check_page "episode list after upload" "$BASE/admin/episodes_list.php"
check_page "home page after upload" "$BASE/index.php"
if grep -q '<script>alert(4)' "$OUT"; then fail "script in episode description on home page"; else ok "episode description sanitised"; fi
if grep -q 'Smoke <episode>' "$OUT"; then fail "episode title not escaped on home page"; else ok "episode title escaped on home page"; fi

# --- feed ------------------------------------------------------------------
fetch "$BASE/feed.xml" >/dev/null
# Read from stdin: a Windows php.exe cannot open MSYS paths like /tmp/...
if php -r 'libxml_use_internal_errors(true); $x = simplexml_load_string(stream_get_contents(STDIN)); exit($x && count($x->channel->item) > 0 ? 0 : 1);' <"$OUT"; then
    ok "feed.xml is valid XML with episodes"
else
    fail "feed.xml invalid or empty"
fi
if grep -q 'Smoke &lt;episode&gt; &amp; "quotes"\|Smoke &lt;episode&gt; &amp; &quot;quotes&quot;' "$OUT"; then
    ok "episode title escaped in feed"
else
    fail "episode title not found in feed"
fi
if grep -q '<x>' "$OUT"; then fail "XML injection via explicit field"; else ok "explicit field sanitised"; fi

echo
if [ "$FAILS" -eq 0 ]; then
    echo "ALL OK"
else
    echo "$FAILS FAILED"
fi
exit "$FAILS"
