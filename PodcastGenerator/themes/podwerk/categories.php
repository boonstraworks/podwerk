<?php

############################################################
# Podwerk theme — categories: the list, or one category's episodes.
# Included by the site's categories.php with data in scope.
############################################################

require_once __DIR__ . '/_lib.php';

$strings = array(
    'download' => isset($download) ? $download : _('Download'),
    'editdelete' => isset($editdelete) ? $editdelete : _('Edit/Delete (Admin)'),
    'playlabel' => _('Play %s'),
);
$loggedin = isset($_SESSION['username']);
$selectedCat = isset($_GET['cat']) ? $_GET['cat'] : null;

$catTitle = _('Categories');
if ($selectedCat !== null) {
    if ($selectedCat === 'all') {
        $catTitle = _('All Episodes');
    } elseif (isset($categories_arr[$selectedCat])) {
        $catTitle = $categories_arr[$selectedCat];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($config['feed_language'], 0, 2)) ?>">
<head>
    <?php pw_head($config, $config['podcast_title'] . ' — ' . $catTitle); ?>
</head>
<body>
    <?php pw_header($config, $categories); ?>
    <main class="wrap">
    <?php if ($selectedCat === null) { ?>
        <div class="section-head"><h2><?= _('Categories') ?></h2></div>
        <ul class="cats">
            <li><a href="categories.php?cat=all"><?= pw_icon('layers', 20) ?> <?= _('All Episodes') ?></a></li>
            <?php foreach ($categories_arr as $key => $value) { ?>
                <li><a href="categories.php?cat=<?= htmlspecialchars(rawurlencode($key)) ?>"><?= pw_icon('layers', 20) ?> <?= htmlspecialchars($value) ?></a></li>
            <?php } ?>
        </ul>
    <?php } else { ?>
        <a class="back" href="categories.php"><?= pw_icon('arrow-left', 18) ?> <?= _('Categories') ?></a>
        <div class="section-head"><h2><?= htmlspecialchars($catTitle) ?></h2></div>
        <?php if (empty($episode_chunk)) { ?>
            <div class="empty"><h2><?= _('No episodes uploaded yet') ?></h2></div>
        <?php } else { ?>
            <div class="tracklist">
                <?php foreach ($episode_chunk as $ep) { pw_track($ep['episode'], $config, $link, $strings, $loggedin); } ?>
            </div>
        <?php } ?>
    <?php } ?>
    </main>
    <?php pw_footer($config, $app_name, $app_url); ?>
</body>
</html>
