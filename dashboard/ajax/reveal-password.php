<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
//
// Reveals the password recorded against a single address-book entry.
//
// This proxies the API's audited reveal endpoint, so the API stays the only place
// that decrypts and the access check lives in one spot. The dashboard never sees
// a stored blob, only the plaintext returned for this one request.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

session_init();
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$abGuid = $_GET['ab']   ?? '';
$peerId = $_GET['peer'] ?? '';

if ($abGuid === '' || $peerId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing address book or entry']);
    exit;
}

$resp = api_post("/ab/peer/reveal/$abGuid/" . rawurlencode($peerId));

if (!api_ok($resp)) {
    http_response_code($resp['_status'] ?? 500);
    echo json_encode(['error' => api_error($resp)]);
    exit;
}

if (($resp['data'] ?? '') === 'empty') {
    echo json_encode(['password' => null, 'empty' => true]);
    exit;
}

echo json_encode(['password' => $resp['password'] ?? null]);
