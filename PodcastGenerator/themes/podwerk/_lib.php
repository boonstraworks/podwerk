<?php

############################################################
# PODCAST GENERATOR / Podwerk
#
# Podwerk theme — shared helpers and partials.
# Expects the usual theme variables in scope: $config, $app_name,
# $app_url, $episodes, $link, $buttons and the translation strings.
############################################################

// Full URL of an episode's cover: its own image, a cover file matching the
// episode id, or the podcast cover as a fallback.
function pw_cover($episode, $config)
{
    if (!empty($episode['imgPG'])) {
        return $episode['imgPG'];
    }
    foreach (array('jpg', 'png') as $ext) {
        if (file_exists($config['absoluteurl'] . $config['img_dir'] . $episode['fileid'] . '.' . $ext)) {
            return $config['url'] . $config['img_dir'] . $episode['fileid'] . '.' . $ext;
        }
    }
    return $config['url'] . $config['img_dir'] . $config['podcast_cover'];
}

// URL of an episode's own page.
function pw_episode_url($episode, $config, $link)
{
    return $config['indexfile'] . '?' . $link . '=' . rawurlencode($episode['filename']);
}

// A friendlier date than the stored string, when it can be parsed.
function pw_date($raw)
{
    $raw = trim((string) $raw);
    $ts = strtotime($raw);
    return $ts ? date('j M Y', $ts) : $raw;
}

// 'audio', 'video' or 'invalid' for an episode file.
function pw_media_type($episode, $config)
{
    $mime = getmime($config['absoluteurl'] . $config['upload_dir'] . $episode['filename']);
    if (!$mime) {
        return array('invalid', '');
    }
    if (substr($mime, 0, 5) === 'video') {
        return array('video', $mime);
    }
    if (substr($mime, 0, 5) === 'audio' || $mime === 'application/ogg') {
        return array('audio', $mime);
    }
    return array('invalid', $mime);
}

// A small inline SVG icon. Kept here so the theme needs no icon font.
function pw_icon($name, $size = 20)
{
    $paths = array(
        'play' => '<path d="M8 5v14l11-7z"/>',
        'rss' => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="5" cy="19" r="1.6"/>',
        'download' => '<path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'search' => '<circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.2-3.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        'arrow-left' => '<path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Zm9 9-9 5-9-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
    );
    $inner = isset($paths[$name]) ? $paths[$name] : '';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}

// Shared <head>. $pageTitle and $pageDesc may be set by the caller.
function pw_head($config, $pageTitle = null, $pageDesc = null)
{
    $title = $pageTitle !== null ? $pageTitle : $config['podcast_title'];
    $desc = $pageDesc !== null ? $pageDesc : $config['podcast_subtitle'];
    $tp = htmlspecialchars($config['theme_path']);
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($desc) ?>">
    <meta name="author" content="<?= htmlspecialchars($config['author_name']) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($config['podcast_title']) ?>" href="feed.xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $tp ?>style/theme.css">
    <?php
}

// Top bar with the podcast name and navigation.
function pw_header($config, $categories)
{
    $home = htmlspecialchars($config['indexfile']);
    ?>
    <header class="topbar">
        <div class="wrap topbar-in">
            <a class="brand" href="<?= $home ?>"><?= htmlspecialchars($config['podcast_title']) ?></a>
            <nav class="nav" aria-label="Main">
                <a href="<?= $home ?>">Home</a>
                <?php if (strtolower($config['categoriesenabled']) === 'yes') { ?>
                    <a href="categories.php"><?= htmlspecialchars($categories) ?></a>
                <?php } ?>
                <a href="feed.xml">Subscribe</a>
                <a class="nav-admin" href="admin/">Admin</a>
            </nav>
        </div>
    </header>
    <?php
}

function pw_footer($config, $app_name, $app_url)
{
    ?>
    <footer class="footer">
        <div class="wrap">
            <p>
                <?= htmlspecialchars($config['podcast_title']) ?> &middot;
                published with <a href="<?= htmlspecialchars($app_url) ?>"><?= htmlspecialchars($app_name) ?></a>,
                open source podcast software.
            </p>
        </div>
    </footer>
    <script src="<?= htmlspecialchars($config['theme_path']) ?>app.js" defer></script>
    <?php
}

// One episode row in the tracklist. Used on the home and category pages.
function pw_track($episode, $config, $link, $strings, $loggedin)
{
    list($type, $mime) = pw_media_type($episode, $config);
    if ($type === 'invalid') {
        return;
    }
    $url = pw_episode_url($episode, $config, $link);
    $cover = pw_cover($episode, $config);
    $src = htmlspecialchars($config['upload_dir'] . rawurlencode($episode['filename']));
    $num = isset($episode['episodeNumPG']) ? trim((string) $episode['episodeNumPG']) : '';
    $duration = isset($episode['fileInfoPG']['duration']) ? trim((string) $episode['fileInfoPG']['duration']) : '';
    ?>
    <article class="track">
        <div class="track-num"><?= $num !== '' ? htmlspecialchars($num) : '' ?></div>
        <a class="track-cover" href="<?= htmlspecialchars($url) ?>" tabindex="-1" aria-hidden="true">
            <img loading="lazy" src="<?= htmlspecialchars($cover) ?>" alt="">
        </a>
        <div class="track-main">
            <h3 class="track-title"><a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($episode['titlePG']) ?></a></h3>
            <p class="track-meta">
                <time><?= htmlspecialchars(pw_date($episode['moddate'])) ?></time>
            </p>
            <?php if (!empty($episode['shortdescPG'])) { ?>
                <p class="track-desc"><?= $episode['shortdescPG'] ?></p>
            <?php } ?>
            <?php if ($loggedin) { ?>
                <a class="link-admin" href="admin/episodes_edit.php?name=<?= htmlspecialchars(rawurlencode($episode['filename'])) ?>"><?= htmlspecialchars($strings['editdelete']) ?></a>
            <?php } ?>
        </div>
        <div class="track-time"><?= $duration !== '' ? htmlspecialchars($duration) : '' ?></div>
        <?php if (strtolower($config['enablestreaming']) === 'yes' && $type === 'audio') { ?>
            <button class="play" type="button"
                    data-src="<?= $src ?>" data-type="<?= htmlspecialchars($mime) ?>"
                    aria-label="<?= htmlspecialchars(sprintf($strings['playlabel'], $episode['titlePG'])) ?>">
                <?= pw_icon('play', 22) ?>
            </button>
        <?php } else { ?>
            <a class="play play-link" href="<?= htmlspecialchars($url) ?>" aria-label="<?= htmlspecialchars($episode['titlePG']) ?>"><?= pw_icon('play', 22) ?></a>
        <?php } ?>
    </article>
    <?php
}
