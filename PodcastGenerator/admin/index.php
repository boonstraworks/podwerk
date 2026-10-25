<?php
############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################
require 'checkLogin.php';
require '../core/include_admin.php';
?>
<!DOCTYPE html>
<html>

<head>
    <title><?= htmlspecialchars($config['podcast_title']) ?> - Admin</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="../core/bootstrap/style.css">
    <style>
        iframe {
            width: 1000px;
            height: 500px;
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="<?= $config['url'] ?>favicon.ico">
</head>

<body>
    <?php
    include 'js.php';
    include 'navbar.php';
    ?>
    <br>
    <div class="container">
        <?php //# %s is the name of the software (Podwerk), which is not translated. ?>
        <h1><?= sprintf(_('Welcome to your %s Admin Interface'), $app_name) ?></h1>
        <?php if ($config['enablepgnewsinadmin'] == 'yes' && $news_url != '') { ?>
            <iframe width="100%" src="<?= htmlspecialchars($news_url) ?>" sandbox="allow-popups allow-popups-to-escape-sandbox"></iframe>
        <?php } ?>

        <div class="row align-items-end justify-content-end">
            <div class="col-3">
                <small class="text-muted">
                    <a href="<?= htmlspecialchars($app_url) ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($app_name . ' v' . $config['podcastgen_version']) ?>
                    </a>
                </small>
            </div>
        </div>
    </div>
</body>

</html>