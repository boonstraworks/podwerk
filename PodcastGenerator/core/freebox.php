<?php

############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################
require_once __DIR__ . '/misc/html_sanitizer.php';

function getFreebox($path = null)
{
    $_config = getConfig($path . 'config.php');
    if ($_config['freebox'] != 'yes') {
        return null;
    }
    // Also clean content saved by older versions
    return sanitizeHtml(file_get_contents($path . 'freebox-content.txt'));
}

function updateFreebox($path, $content)
{
    return file_put_contents($path . 'freebox-content.txt', sanitizeHtml($content));
}
