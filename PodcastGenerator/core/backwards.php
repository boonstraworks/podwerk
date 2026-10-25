<?php

############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################

function backwards_3_1_to_3_2_9($absoluteurl)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_destroy();
    global $config;
    global $version;

    // 3.2.9 and later 3.2.x releases share the same config format, so only
    // the version number has to be updated
    if (
        version_compare($config['podcastgen_version'], '3.2.9', '>=')
        && version_compare($config['podcastgen_version'], $version, '<')
    ) {
        updateConfig($absoluteurl . 'config.php', 'podcastgen_version', $version);
        return;
    }

    // Quit if version is not 3.1.x or 3.2
    if (
        !(
            $config['podcastgen_version'] == '3.1'
            || substr($config['podcastgen_version'], 0, 4) == '3.1.'
            || $config['podcastgen_version'] == '3.2'
            || $config['podcastgen_version'] == '3.2.1'
            || $config['podcastgen_version'] == '3.2.2'
            || $config['podcastgen_version'] == '3.2.3'
            || $config['podcastgen_version'] == '3.2.4'
            || $config['podcastgen_version'] == '3.2.5'
            || $config['podcastgen_version'] == '3.2.6'
            || $config['podcastgen_version'] == '3.2.7'
            || $config['podcastgen_version'] == '3.2.8'
        )
    ) {
        return;
    }

    // Fix up podcast language
    $languages = simplexml_load_file('../components/supported_languages/podcast_languages.xml');

    $matchedLanguage = null;
    foreach ($languages as $lang) {
        if ($config['feed_language'] == $lang->code) {
            $matchedLanguage = $lang;
            break;
        }
    }

    if ($matchedLanguage == null) {
        // Reset back to default of English, since we don't have a clue about
        // what language this should have been.
        $config['feed_language'] = 'en';
    } elseif (property_exists($matchedLanguage, 'alias')) {
        // Change to correct language code when a bad / obsolete code has been
        // used.
        $config['feed_language'] = $matchedLanguage->alias;
    }

    // Default sort method for episodes in the RSS feed
    if (!isset($config['feed_sort'])) {
        $config['feed_sort'] = 'timestamp';
    }

    // Other defaults for config settings added in 3.2
    if (!isset($config['customtagsenabled'])) {
        $config['customtagsenabled'] = 'no';
    }
    if (!isset($config['timezone'])) {
        $config['timezone'] = '';
    }
    if (!isset($config['podcast_guid'])) {
        $config['podcast_guid'] = '';
    }
    if (!isset($config['podcast_cover'])) {
        $config['podcast_cover'] = 'itunes_image.jpg';
    }
    if (!isset($config['feed_locked'])) {
        $config['feed_locked'] = '';
    }
    if (!isset($config['websub_server'])) {
        $config['websub_server'] = '';
    }
    if (!isset($config['pi_api_key'])) {
        $config['pi_api_key'] = '';
    }
    if (!isset($config['pi_api_secret'])) {
        $config['pi_api_secret'] = '';
    }
    if (!isset($config['pi_podcast_id'])) {
        $config['pi_podcast_id'] = 0;
    }

    $config_php = "<?php
\$podcastgen_version = " . configLiteral($version) . "; // Version

\$first_installation = " . configLiteral($config['first_installation']) . ";

\$installationKey = " . configLiteral($config['installationKey']) . ";

\$scriptlang = " . configLiteral($config['scriptlang']) . ";

\$url = " . configLiteral($config['url']) . ";

\$absoluteurl = " . configLiteral($config['absoluteurl']) . "; // The location on the server

\$theme_path = " . configLiteral($config['theme_path']) . ";

\$upload_dir = " . configLiteral($config['upload_dir']) . "; // 'media/' the default folder (Trailing slash required). Set chmod 755

\$img_dir = " . configLiteral($config['img_dir']) . "; // (Trailing slash required). Set chmod 755

\$feed_dir = " . configLiteral($config['feed_dir']) . "; // Where to create feed.xml (empty value = root directory). Set chmod 755

\$max_recent = " . configLiteral($config['max_recent']) . "; // How many file to show in the home page

\$recent_episode_in_feed = " . configLiteral($config['recent_episode_in_feed']) . "; // How many file to show in the XML feed (1,2,5 etc.. or 'All')

\$episodeperpage = " . configLiteral($config['episodeperpage']) . ";

\$enablestreaming = " . configLiteral($config['enablestreaming']) . "; // Enable mp3 streaming? ('yes' or 'no')

\$freebox = " . configLiteral($config['freebox']) . "; // enable freely customizable box

\$enablepgnewsinadmin = " . configLiteral($config['enablepgnewsinadmin']) . ";

\$strictfilenamepolicy = " . configLiteral($config['strictfilenamepolicy']) . "; // strictly rename files (just characters A to Z and numbers) 

\$categoriesenabled = " . configLiteral($config['categoriesenabled']) . ";

\$cronAutoIndex = " . configLiteral($config['cronAutoIndex']) . "; //Auto Index New Episodes via Cron

\$cronAutoRegenerateRSS = " . configLiteral($config['cronAutoRegenerateRSS']) . "; //Auto regenerate RSS via Cron

\$indexfile = " . configLiteral($config['indexfile']) . ";    // Path of the index file

\$podcastPassword = " . configLiteral($config['podcastPassword']) . ";       // Password to protect the podcast generator webpages, this will NOT protect the audio or XML files. Leave blank to disable.

\$customtagsenabled = " . configLiteral($config['customtagsenabled']) . ";   // Advanced functionality for custom RSS tag input

\$timezone = " . configLiteral($config['timezone']) . ";              // Timezone used for displaying dates and times

#####################
# XML Feed stuff

\$podcast_guid = " . configLiteral($config['podcast_guid']) . "; // Globally unique identifier for your podcast

\$podcast_title = " . configLiteral($config['podcast_title']) . ";

\$podcast_subtitle = " . configLiteral($config['podcast_subtitle']) . ";

\$podcast_description = " . configLiteral($config['podcast_description']) . ";

\$podcast_cover = " . configLiteral($config['podcast_cover']) . ";

\$author_name = " . configLiteral($config['author_name']) . ";

\$author_email = " . configLiteral($config['author_email']) . ";

# The e-mail of the technical admin of the podcast
\$webmaster = " . configLiteral($config['author_email']) . ";

\$itunes_category[0] = " . configLiteral($config['itunes_category[0]']) . "; // iTunes categories (mainCategory:subcategory)
\$itunes_category[1] = " . configLiteral($config['itunes_category[1]']) . ";
\$itunes_category[2] = " . configLiteral($config['itunes_category[2]']) . ";

\$link = " . configLiteral($config['link']) . "; // permalink URL of single episode (appears in the <link> and <guid> tags in the feed)

\$feed_language = " . configLiteral($config['feed_language']) . ";

\$feed_sort = " . configLiteral($config['feed_sort']) . "; // sort method used to order episodes in the feed (by timestamp or by season/episode number)

\$feed_locked = " . configLiteral($config['feed_locked']) . "; // podcast:locked status ('yes', 'no', '' for off)

\$copyright = " . configLiteral($config['copyright']) . ";   // Your copyright notice (e.g CC-BY)

\$feed_encoding = " . configLiteral($config['feed_encoding']) . ";

\$explicit_podcast = " . configLiteral($config['explicit_podcast']) . "; //does your podcast contain explicit language? ('yes' or 'no')

\$users_json = " . configLiteral($config['users_json']) . ";

#####################
# WebSub

\$websub_server = " . configLiteral($config['websub_server']) . ";

#####################
# Podcast Index

\$pi_api_key = " . configLiteral($config['pi_api_key']) . ";
\$pi_api_secret = " . configLiteral($config['pi_api_secret']) . ";

\$pi_podcast_id = " . (int) $config['pi_podcast_id'] . "; // is the podcast in Podcast Index? This is its show ID there.

// END OF CONFIG
";
    file_put_contents($absoluteurl . 'config.php', $config_php);

    if (!file_exists($absoluteurl . 'customtags.xml')) {
        $catfile = '<?xml version="1.0" encoding="utf-8"?>
<PodcastGenerator>
    <customfeedtags><![CDATA[]]></customfeedtags>
</PodcastGenerator>';
        file_put_contents($absoluteurl . 'customtags.xml', $catfile);
    }
}
