<?php

############################################################
# Podwerk theme — home page and single episode view.
# Included by the site's index.php with all data in scope.
############################################################

require_once __DIR__ . '/_lib.php';

$strings = array(
    'download' => isset($download) ? $download : _('Download'),
    'editdelete' => isset($editdelete) ? $editdelete : _('Edit/Delete (Admin)'),
    'playlabel' => _('Play %s'),
);
$loggedin = isset($_SESSION['username']);
$showEpisode = isset($_GET[$link]);

// Resolve the selected episode (if any) for the page title and meta.
$selected = null;
if ($showEpisode) {
    foreach ($episodes as $ep) {
        if ($ep['episode']['filename'] == $_GET[$link]) {
            $selected = $ep['episode'];
            break;
        }
    }
}
$pageTitle = $selected ? $config['podcast_title'] . ' — ' . $selected['titlePG'] : $config['podcast_title'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($config['feed_language'], 0, 2)) ?>">
<head>
    <?php
    pw_head($config, $pageTitle, $config['podcast_subtitle']);
    $ogImg = $config['url'] . $config['img_dir'] . $config['podcast_cover'];
    if ($selected) {
        $ogImg = pw_cover($selected, $config);
    }
    ?>
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= htmlspecialchars($ogImg) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($config['podcast_description']) ?>">
</head>
<body>
    <?php pw_header($config, $categories); ?>
    <main class="wrap">
    <?php if ($showEpisode) {
        /* ---------- single episode ---------- */
        if (!$selected) {
            echo '<div class="empty"><h2>' . _('Episode does not exist') . '</h2><p><a href="' . htmlspecialchars($config['indexfile']) . '">' . _('Back to all episodes') . '</a></p></div>';
        } else {
            list($type, $mime) = pw_media_type($selected, $config);
            $cover = pw_cover($selected, $config);
            $num = trim((string) ($selected['episodeNumPG'] ?? ''));
            $season = trim((string) ($selected['seasonNumPG'] ?? ''));
            $ext = strtoupper(pathinfo($selected['filename'], PATHINFO_EXTENSION));
            $dur = trim((string) ($selected['fileInfoPG']['duration'] ?? ''));
            $sizemb = trim((string) ($selected['fileInfoPG']['size'] ?? ''));
            ?>
            <a class="back" href="<?= htmlspecialchars($config['indexfile']) ?>"><?= pw_icon('arrow-left', 18) ?> <?= _('All episodes') ?></a>
            <article class="episode">
                <div class="episode-art"><img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($selected['titlePG']) ?>"></div>
                <div>
                    <?php if ($num !== '') { ?><p class="episode-num"><?= $season !== '' ? htmlspecialchars(sprintf(_('Season %s'), $season)) . ' · ' : '' ?><?= htmlspecialchars(sprintf(_('Episode %s'), $num)) ?></p><?php } ?>
                    <h1><?= htmlspecialchars($selected['titlePG']) ?></h1>
                    <div class="episode-meta">
                        <span class="tag"><?= htmlspecialchars(pw_date($selected['moddate'])) ?></span>
                        <?php if ($dur !== '') { ?><span class="tag"><?= htmlspecialchars($dur) ?></span><?php } ?>
                        <?php if ($ext !== '') { ?><span class="tag"><?= htmlspecialchars($ext) ?></span><?php } ?>
                        <?php if ($sizemb !== '' && $sizemb !== '0') { ?><span class="tag"><?= htmlspecialchars($sizemb) ?> MB</span><?php } ?>
                    </div>
                    <?php if (strtolower($config['enablestreaming']) === 'yes' && $type !== 'invalid') { ?>
                        <div class="player">
                            <?php if ($type === 'video') { ?>
                                <video controls><source src="<?= htmlspecialchars($config['upload_dir'] . rawurlencode($selected['filename'])) ?>" type="<?= htmlspecialchars($mime) ?>"></video>
                            <?php } else { ?>
                                <audio controls preload="none"><source src="<?= htmlspecialchars($config['upload_dir'] . rawurlencode($selected['filename'])) ?>" type="<?= htmlspecialchars($mime) ?>"></audio>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div class="prose">
                        <?php if (!empty($selected['longdescPG'])) {
                            echo $selected['longdescPG'];
                        } elseif (!empty($selected['shortdescPG'])) {
                            echo '<p>' . $selected['shortdescPG'] . '</p>';
                        } ?>
                    </div>
                    <div class="episode-actions">
                        <a class="btn" href="media/<?= htmlspecialchars(rawurlencode($selected['filename'])) ?>" download><?= pw_icon('download', 18) ?> <?= htmlspecialchars($strings['download']) ?></a>
                        <?php if ($loggedin) { ?><a class="btn" href="admin/episodes_edit.php?name=<?= htmlspecialchars(rawurlencode($selected['filename'])) ?>"><?= htmlspecialchars($strings['editdelete']) ?></a><?php } ?>
                    </div>
                </div>
            </article>
        <?php }
    } else {
        /* ---------- home: hero + tracklist ---------- */
        $freebox = getFreebox();
        $latest = count($episodes) ? $episodes[0]['episode'] : null;
        ?>
        <section class="hero">
            <div class="hero-art"><img src="<?= htmlspecialchars($config['url'] . $config['img_dir'] . $config['podcast_cover']) ?>" alt="<?= htmlspecialchars($config['podcast_title']) ?>"></div>
            <div>
                <h1 class="hero-title"><?= htmlspecialchars($config['podcast_title']) ?></h1>
                <?php if (!empty($config['podcast_subtitle'])) { ?><p class="hero-sub"><?= htmlspecialchars($config['podcast_subtitle']) ?></p><?php } ?>
                <?php if (!empty($config['podcast_description'])) { ?><p class="hero-desc"><?= htmlspecialchars($config['podcast_description']) ?></p><?php } ?>
                <div class="hero-actions">
                    <?php if ($latest && strtolower($config['enablestreaming']) === 'yes') { ?>
                        <a class="btn btn-primary" href="<?= htmlspecialchars(pw_episode_url($latest, $config, $link)) ?>"><?= pw_icon('play', 20) ?> <?= _('Latest episode') ?></a>
                    <?php } ?>
                    <a class="btn" href="feed.xml"><?= pw_icon('rss', 18) ?> RSS</a>
                    <?php foreach ($buttons as $item) {
                        if (strtolower(trim((string) $item->name)) === 'rss') { continue; } ?>
                        <a class="btn" href="external.php?name=<?= htmlspecialchars(rawurlencode($item->name)) ?>"><?= htmlspecialchars($item->name) ?></a>
                    <?php } ?>
                </div>
                <?php if ($freebox != null) { ?><div class="hero-note"><?= $freebox ?></div><?php } ?>
            </div>
        </section>

        <div class="section-head">
            <h2><?= isset($_GET['search']) ? _('Search results') : _('Episodes') ?></h2>
            <div class="search">
                <?= pw_icon('search', 18) ?>
                <form action="<?= htmlspecialchars($config['indexfile']) ?>">
                    <input type="search" name="search" placeholder="<?= htmlspecialchars($search) ?>" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" aria-label="<?= htmlspecialchars($search) ?>">
                </form>
            </div>
        </div>

        <?php if (isset($no_episodes)) { ?>
            <div class="empty"><h2><?= htmlspecialchars($no_episodes) ?></h2>
                <?php if (isset($_GET['search'])) { ?><p><a href="<?= htmlspecialchars($config['indexfile']) ?>"><?= _('Back to all episodes') ?></a></p><?php } ?>
            </div>
        <?php } else { ?>
            <div class="tracklist">
                <?php foreach ($episode_chunk as $ep) { pw_track($ep['episode'], $config, $link, $strings, $loggedin); } ?>
            </div>
            <?php if (count($episodes) > intval($config['episodeperpage'])) { ?>
                <nav class="pager" aria-label="Pages">
                    <?php for ($j = 0; $j < count($splitted_episodes); $j++) { ?>
                        <a href="<?= htmlspecialchars($config['indexfile'] . '?page=' . ($j + 1)) ?>"><?= $j + 1 ?></a>
                    <?php } ?>
                </nav>
            <?php } ?>
        <?php } ?>
    <?php } ?>
    </main>
    <?php pw_footer($config, $app_name, $app_url); ?>
</body>
</html>
