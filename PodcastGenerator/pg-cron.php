<?php

############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################
require 'core/include.php';
// Only work when cronAutoIndex is enabled
if (isset($_GET['key']) && $config['cronAutoIndex'] == "1") {
    if (
        is_string($_GET['key']) && $config['installationKey'] != ''
        && hash_equals($config['installationKey'], $_GET['key'])
    ) {
        indexEpisodes($config);
        generateRSS();
    }
}
