<?php

############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################

// Minimal Podcast Index API client.
// Replaces podcastindex/podcastindex-php, which pinned Guzzle 6 and pulled
// in packages with known security advisories. Only the two endpoints we
// actually use are needed, so plain cURL is enough.

define('PODCASTINDEX_API_URL', 'https://api.podcastindex.org/api/1.0/');

// Performs a GET request against the Podcast Index API.
// Returns an array with 'code' (HTTP status) and 'json' (decoded body or
// null), or throws an Exception when the request itself fails.
function podcastIndexGet($endpoint, $query, $key, $secret, $userAgent)
{
    if (!function_exists('curl_init')) {
        throw new Exception('cURL functions are not available.');
    }

    $timestamp = time();
    $headers = array(
        'User-Agent: ' . $userAgent,
        'X-Auth-Key: ' . $key,
        'X-Auth-Date: ' . $timestamp,
        'Authorization: ' . sha1($key . $secret . $timestamp),
    );

    $handle = curl_init();
    curl_setopt_array($handle, array(
        CURLOPT_URL => PODCASTINDEX_API_URL . ltrim($endpoint, '/') . '?' . http_build_query($query),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ));
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    closeCurlHandle($handle);

    if ($body === false) {
        throw new Exception('Podcast Index request failed: ' . $error);
    }

    return array(
        'code' => $code,
        'json' => json_decode($body),
    );
}

// curl_close() is a no-op since PHP 8.0 and deprecated since PHP 8.5.
function closeCurlHandle($handle)
{
    if (PHP_VERSION_ID < 80000) {
        curl_close($handle);
    }
}
