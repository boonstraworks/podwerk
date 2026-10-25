<?php

############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################
require '../core/include_admin.php';
$releasesUrl = $app_url . '/releases';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= _('Password Reset') ?></title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="../core/bootstrap/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars($config['url']) ?>favicon.ico">
</head>
<body>
    <div class="container">
        <h1><?= _('Reset your password') ?></h1>
        <h3><?= _('No password, no problem!') ?></h3>
        <p>
        <?= _('We offer a tool which allows you to easily recover your password. However, the danger of this is that as long as this file is online, anyone can reset this password so this file shouldn\'t be online that long.') ?>
        <br>
        <?= sprintf(_('In the ZIP file of this version which you downloaded there is a file in the %s folder called %s. Upload this file to into the admin directory with the name %s'), "contrib/recover", "reset.php", "reset.php") ?>
        <?php //# %1$s is the name of the software (Podwerk), which is not translated. %2$s is a link. ?>
        <?= sprintf(
            _('If you no longer have this ZIP file, download the same version again from the %1$s releases page: %2$s'),
            htmlspecialchars($app_name),
            '<a href="' . htmlspecialchars($releasesUrl) . '">' . htmlspecialchars($releasesUrl) . '</a>'
        ) ?>
        </p>
    </div>
</body>
</html>
