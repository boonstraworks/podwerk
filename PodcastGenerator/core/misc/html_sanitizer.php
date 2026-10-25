<?php

############################################################
# PODCAST GENERATOR
#
# Created by Alberto Betella and Emil Engler
# http://www.podcastgenerator.net
#
# This is Free Software released under the GNU/GPL License.
############################################################

// Allowlist-based HTML cleaner for fields that are meant to contain some
// formatting (the freebox and episode descriptions). Scripts, event
// handlers, frames, forms and javascript: links are removed; harmless
// formatting is kept. Cleaning already clean HTML gives the same result.

function sanitizeHtml($html)
{
    $html = (string) $html;
    if (trim($html) === '' || strpbrk($html, '<&') === false) {
        return $html;
    }
    if (!class_exists('DOMDocument')) {
        // Without the DOM extension, fall back to plain text
        return htmlspecialchars(strip_tags($html));
    }

    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    // The XML declaration makes libxml read the input as UTF-8
    $doc->loadHTML(
        '<?xml encoding="utf-8"?><pgsanitize>' . $html . '</pgsanitize>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = null;
    foreach ($doc->childNodes as $node) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $root = $node;
            break;
        }
    }
    if ($root === null) {
        return '';
    }
    sanitizeHtmlChildren($root);

    $clean = '';
    foreach ($root->childNodes as $child) {
        $clean .= $doc->saveHTML($child);
    }
    return $clean;
}

function sanitizeHtmlChildren(DOMNode $parent)
{
    // Tags kept as they are (with only the attributes listed)
    static $allowed = array(
        'a' => array('href', 'title', 'target', 'rel'),
        'img' => array('src', 'alt', 'title', 'width', 'height'),
        'td' => array('colspan', 'rowspan'),
        'th' => array('colspan', 'rowspan'),
        'abbr' => array('title'),
        'b' => array(), 'blockquote' => array(), 'br' => array(), 'cite' => array(),
        'code' => array(), 'del' => array(), 'div' => array(), 'em' => array(),
        'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(),
        'h5' => array(), 'h6' => array(), 'hr' => array(), 'i' => array(),
        'li' => array(), 'ol' => array(), 'p' => array(), 'pre' => array(),
        's' => array(), 'small' => array(), 'span' => array(), 'strong' => array(),
        'sub' => array(), 'sup' => array(), 'table' => array(), 'tbody' => array(),
        'thead' => array(), 'tr' => array(), 'u' => array(), 'ul' => array(),
    );
    // Attributes allowed on every kept tag
    static $global = array('class', 'style');
    // Tags removed together with their content; other unknown tags are
    // unwrapped, so their text stays
    static $dropped = array(
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base', 'title',
    );

    // Copy the list first: the loop changes the tree
    $children = array();
    foreach ($parent->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            // comments, processing instructions, ...
            $parent->removeChild($child);
            continue;
        }

        $tag = strtolower($child->nodeName);
        if (in_array($tag, $dropped, true)) {
            $parent->removeChild($child);
            continue;
        }
        if (!isset($allowed[$tag])) {
            sanitizeHtmlChildren($child);
            while ($child->firstChild) {
                $parent->insertBefore($child->firstChild, $child);
            }
            $parent->removeChild($child);
            continue;
        }

        $keep = array_merge($allowed[$tag], $global);
        $names = array();
        foreach ($child->attributes as $attribute) {
            $names[] = $attribute->name;
        }
        foreach ($names as $name) {
            $value = $child->getAttribute($name);
            if (
                !in_array(strtolower($name), $keep, true)
                || (in_array($name, array('href', 'src'), true) && !isSafeHtmlUrl($value))
                || ($name === 'style' && preg_match('/expression|javascript|behavior|binding|@import/i', $value))
            ) {
                $child->removeAttribute($name);
            }
        }
        if ($tag === 'a' && strtolower($child->getAttribute('target')) === '_blank') {
            $child->setAttribute('rel', 'noopener noreferrer');
        }

        sanitizeHtmlChildren($child);
    }
}

// Only http(s), mailto and relative URLs are allowed in href and src
function isSafeHtmlUrl($url)
{
    // Browsers ignore whitespace and control characters inside the scheme
    $compact = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES, 'UTF-8')));
    if (!preg_match('/^([a-z][a-z0-9+.\-]*):/', $compact, $match)) {
        return true; // relative URL or #anchor
    }
    return in_array($match[1], array('http', 'https', 'mailto'), true);
}
