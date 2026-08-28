<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
define('API_BASE',       rtrim(getenv('API_URL')        ?: 'http://skonadesk-api:21114', '/'));
define('APP_NAME',       'SkonaDesk');
define('APP_SECRET',     getenv('APP_SECRET')           ?: '');
define('DATA_PATH',      getenv('DATA_PATH')            ?: '/data');
define('APP_VERSION',    '1.0.0');
define('LANG_DIR',       __DIR__ . '/../lang');

$_apiPublic = getenv('API_PUBLIC_URL');
$_domain    = getenv('DOMAIN') ?: '';
$_isIp      = (bool) preg_match('/^\d+\.\d+\.\d+\.\d+$/', $_domain);
$_scheme    = $_isIp ? 'http' : 'https';
$_port      = $_isIp ? ':21114' : '';
define('API_PUBLIC_URL', rtrim(
    $_apiPublic ?: ($_domain ? $_scheme . '://' . $_domain . $_port : ''),
    '/'
));
define('API_USE_SSL', str_starts_with(API_PUBLIC_URL, 'https://'));
