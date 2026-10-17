<?php

/**
 * A base URL fit to save as `serverurl` and to put in mail and links: http or https, a host name or IP address, an
 * optional port and a plain path; no user info, query, fragment, quotes, whitespace or control characters.
 * Returns it with a trailing slash, as 1.1.2 stored it, or null.
 */
function resurrection_valid_server_url(string $url): ?string {
    if (strlen($url) > 255 || !preg_match('#\A(https?)://([^/:]+|\[[0-9A-Fa-f:.]+\])(?::([0-9]{1,5}))?(/[A-Za-z0-9._~%/+-]*)?\z#i', $url, $match)) return null;
    $host = $match[2];
    $ip = str_starts_with($host, '[') ? filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) : filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $name = preg_match('/\A(?=.{1,253}\z)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\z/', $host) === 1;
    if ($ip === false && !$name) return null;
    if (isset($match[3]) && $match[3] !== '' && ((int)$match[3] < 1 || (int)$match[3] > 65535)) return null;
    return str_ends_with($url, '/') ? $url : $url . '/';
}

/**
 * The configured `serverurl`, or null when it is unset or not a valid base URL. Never derived from the request:
 * 1.1.2's petition and referral pages built it from SERVER_NAME, SERVER_PORT and REQUEST_URI when it was unset and
 * saved that as the global setting, so any visitor's Host header could set the URL the game puts in its mail. The
 * value comes only from installation or the game settings editor. A configured value without a trailing slash is
 * saved with one, as 1.1.2 did.
 */
function resurrection_server_url(): ?string {
    global $settings;
    if (!is_array($settings)) loadsettings();
    // getsetting() would save its default for an unset setting; read the stored value only.
    if (!isset($settings['serverurl'])) return null;
    $stored = (string)$settings['serverurl'];
    $url = resurrection_valid_server_url($stored);
    if ($url !== null && $url !== $stored) savesetting("serverurl", $url);
    return $url;
}

/** 1.1.2's request-built base URL, validated, for display to the requesting player only. Never saved. */
function resurrection_request_server_url(): ?string {
    $port = (string)($_SERVER['SERVER_PORT'] ?? '80');
    return resurrection_valid_server_url("http://" . ($_SERVER['SERVER_NAME'] ?? '') . ($port == 80 ? "" : ":" . $port) .
        dirname((string)($_SERVER['REQUEST_URI'] ?? '/')));
}
