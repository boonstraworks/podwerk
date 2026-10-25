<?php

############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################
// Turns a value into a PHP literal that is safe to write into config.php.
// Strings are single-quoted with \ and ' escaped. Line breaks are replaced
// by spaces, because the config file is parsed line by line.
function configLiteral($value)
{
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (!is_scalar($value)) {
        $value = '';
    }
    $value = str_replace(array("\r\n", "\r", "\n"), ' ', (string) $value);
    return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $value) . "'";
}

// Reverses the escaping of configLiteral(). \" and \$ are unescaped as well,
// because config files written by Podcast Generator 3.2.9 and older contain
// those sequences (for example in users_json).
function configUnescape($value)
{
    return preg_replace('/\\\\([\\\\\'"$])/', '$1', $value);
}

// Config keys that must never be changed through a settings form
function isProtectedConfigKey($key)
{
    return in_array($key, array(
        'podcastgen_version',
        'first_installation',
        'installationKey',
        'url',
        'absoluteurl',
        'theme_path',
        'upload_dir',
        'img_dir',
        'feed_dir',
        'indexfile',
        'users_json',
    ), true);
}

// This function updates the config
function updateConfig($path, $key, $value, $eval_null = false)
{
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    for ($i = 0; $i < count($lines); $i++) {
        // Skip empty lines
        if (strlen($lines[$i]) == 0) {
            continue;
        }
        // Skip comment lines
        if ($lines[$i][0] == '/' || $lines[$i][0] == '#') {
            continue;
        }

        // Remove tab at the beginning
        if ($lines[$i][0] == "\t") {
            $lines[$i] = substr($lines[$i], 1);
        }

        // Get the actual key (exact match, not just a common prefix)
        if (strpos($lines[$i], '$' . $key . ' = ') === 0) {
            // Keep the trailing comment, if the current value can be parsed
            $comment = '';
            $literal = '(?:\'(?:[^\'\\\\]|\\\\.)*\'|"[^"]*"|[^\'";]*)';
            if (preg_match('/^\$' . preg_quote($key, '/') . ' = ' . $literal . ';(.*)$/', $lines[$i], $match)) {
                $comment = $match[1];
            }
            $lines[$i] = '$' . $key . ' = ';
            if ($value === 'null' && $eval_null) {
                $lines[$i] .= "'';";
            } else {
                $lines[$i] .= configLiteral($value) . ';';
            }
            // Append comment
            $lines[$i] .= $comment;
        }
    }
    // Finally format the config file and make it "beautiful"
    $configStr = '';
    for ($i = 0; $i < count($lines); $i++) {
        if ($lines[$i] == '') {
            continue;
        }
        // Skip empty lines
        $configStr .= $lines[$i] . "\n\n";
    }
    // Write to the actual config
    if (!file_put_contents($path, $configStr)) {
        return false;
    }
    return true;
}

// This function allows to get config strings
function getConfig($path = 'config.php')
{
    $configmap = array();
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    for ($i = 0; $i < count($lines); $i++) {
        // Skip empty lines
        if (strlen($lines[$i]) == 0) {
            continue;
        }
        // Skip comment and php lines
        if ($lines[$i][0] == '/' || $lines[$i][0] == '#') {
            continue;
        }
        // Remove tab at the beginning
        if ($lines[$i][0] == "\t") {
            $lines[$i] = substr($lines[$i], 1);
        }

        // Strings written by configLiteral()
        if (preg_match('/^\$(.+?) = \'((?:[^\'\\\\]|\\\\.)*)\';/', $lines[$i], $quoted)) {
            $configmap[$quoted[1]] = configUnescape($quoted[2]);
            continue;
        }

        // Legacy parsing, for lines the strict pattern above does not match
        preg_match('/\$(.+?) = ["\'](.+?)?["\'];/', $lines[$i], $strout);    // Get all strings
        preg_match('/\$(.+?) = ([^"\']+);/', $lines[$i], $nonstr); // Get all non strings
        if (count($nonstr) == 3) {
            // Cut of escape chars if there are any
            // Check if $nonstr[2] is "
            if ($nonstr[2] != '"') {
                $nonstr[2] = str_replace("\\", '', $nonstr[2]);
                $configmap[$nonstr[1]] = $nonstr[2];
            } else {
                $configmap[$nonstr[1]] = '';
            }
        } elseif (count($strout) == 3) {
            if ($strout[2] != '"') {
                $strout[2] = str_replace("\\", '', $strout[2]);
                $configmap[$strout[1]] = $strout[2];
            // Make the string empty on errors
            } else {
                $configmap[$strout[1]] = '';
            }
        }
        // If the string is empty
        elseif (count($strout) == 2) {
            $configmap[$strout[1]] = '';
        } else {
            continue;
        }
    }
    // Pop first (<?php) element
    unset($configmap[""]);
    return $configmap;
}

function unsetConfig($path, $key)
{
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    for ($i = 0; $i < count($lines); $i++) {
        // Skip empty lines
        if (strlen($lines[$i]) == 0) {
            continue;
        }
        // Skip comment lines
        if ($lines[$i][0] == '/' || $lines[$i][0] == '#') {
            continue;
        }

        // Remove tab at the beginning
        if ($lines[$i][0] == "\t") {
            $lines[$i] = substr($lines[$i], 1);
        }

        // Get the actual key
        if (substr($lines[$i], 1, strlen($key)) == $key) {
            unset($lines[$i]);
        }
    }
    $configStr = '';
    for ($i = 0; $i < count($lines); $i++) {
        $configStr .= $lines[$i] . "\n";
    }
    // Write to the actual config
    if (!file_put_contents($path, $configStr)) {
        return false;
    }
    return true;
}
