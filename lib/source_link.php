<?php

/**
 * The "View PHP Source" link. 1.1.2 pointed it at source.php, its own source viewer; Resurrection removed that
 * viewer, so the link points at the public source instead. The LoGD license (LICENSE.txt) asks anyone who lets
 * people outside their family and friends play a modified copy to give the complete modified source on request,
 * so an operator who changes the game sets `sourceurl` to their own modified source.
 */
const RESURRECTION_SOURCE_URL = 'https://github.com/jimlunsford/legend-of-the-green-dragon-resurrection';

/**
 * A source URL fit for the link: http or https, a host name or IP address, an optional port, then an optional path,
 * query or fragment without whitespace, control characters, quotes, angle brackets, backslashes or backticks; no
 * user info. Returns it unchanged, or null.
 */
function resurrection_valid_source_url(string $url): ?string {
    if (strlen($url) > 255 || !preg_match('#\Ahttps?://([^/:?\#@\[\]]+|\[[0-9A-Fa-f:.]+\])(?::([0-9]{1,5}))?([/?\#][^\x00-\x20\x7f"\'<>\\\\`]*)?\z#i', $url, $match)) return null;
    $host = $match[1];
    $ip = str_starts_with($host, '[') ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) : filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $name = preg_match('/\A(?=.{1,253}\z)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\z/', $host) === 1;
    if ($ip === false && !$name) return null;
    if (isset($match[2]) && $match[2] !== '' && ((int)$match[2] < 1 || (int)$match[2] > 65535)) return null;
    return $url;
}

/**
 * The configured `sourceurl`, or the public Resurrection repository when it is unset or not a valid http(s) URL.
 * Reads the stored value only: getsetting() would save its default while a page renders.
 */
function resurrection_source_url(): string {
    global $settings;
    if (!is_array($settings)) loadsettings();
    if (!isset($settings['sourceurl'])) return RESURRECTION_SOURCE_URL;
    return resurrection_valid_source_url((string)$settings['sourceurl']) ?? RESURRECTION_SOURCE_URL;
}

/** The template's {source} link: 1.1.2's text and place, the URL escaped. */
function resurrection_source_link(): string {
    $url = htmlspecialchars(resurrection_source_url(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return "<a href='$url' target='_blank' rel='noopener noreferrer'>" . translate_inline("View PHP Source") . "</a>";
}
