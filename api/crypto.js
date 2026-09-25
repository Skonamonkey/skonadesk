// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
//
// Encryption for passwords recorded manually against an address-book entry.
//
// NOTE ON WHAT THIS PROTECTS: RustDesk's own saved connection password lives on
// each client in ~/.config/rustdesk/peers/<id>.toml and is never sent to the
// server, so it is not — and cannot be — held here. What this module encrypts is
// a separate, human-entered note recording a machine's password so it can be
// revealed later by the owner or an admin.
//
// Cipher: AES-256-GCM, random 12-byte IV per record, auth tag stored alongside.
// Key:    HKDF-SHA256 derived from JWT_SECRET with a fixed domain-separation
//         label, so the vault key is cryptographically independent of the key
//         used to sign session tokens even though both share a root secret.
//
// Stored format (single TEXT column):  v1:<iv_b64>:<tag_b64>:<ciphertext_b64>
// The version prefix allows a future key or cipher change without guessing at
// what an existing blob is.

const crypto = require('crypto');

const PREFIX = 'v1';
const ALGO = 'aes-256-gcm';
const IV_LEN = 12;
const KEY_LEN = 32;
const HKDF_INFO = 'skonadesk:ab-peer-password:v1';
const HKDF_SALT = 'skonadesk-vault-salt';

let cachedKey = null;

/**
 * Derive the vault key. Returns null when no usable secret is configured, so
 * callers can fail closed rather than writing something unencrypted.
 */
function vaultKey() {
    if (cachedKey) return cachedKey;

    const secret = process.env.JWT_SECRET || '';
    // 'changeme' is the fallback baked into auth.js — treat it as unset here so a
    // misconfigured deployment cannot silently produce a predictable vault key.
    if (!secret || secret === 'changeme') {
        return null;
    }

    cachedKey = Buffer.from(
        crypto.hkdfSync('sha256', Buffer.from(secret), Buffer.from(HKDF_SALT), Buffer.from(HKDF_INFO), KEY_LEN)
    );
    return cachedKey;
}

/** True when a password can safely be stored or read. */
function vaultAvailable() {
    return vaultKey() !== null;
}

/**
 * Encrypt a plaintext password note.
 * Returns null if the vault key is unavailable — callers must treat null as
 * "refuse the operation", never as "store it as-is".
 */
function encrypt(plaintext) {
    const key = vaultKey();
    if (!key) return null;
    if (typeof plaintext !== 'string' || plaintext === '') return '';

    const iv = crypto.randomBytes(IV_LEN);
    const cipher = crypto.createCipheriv(ALGO, key, iv);
    const ct = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
    const tag = cipher.getAuthTag();

    return [PREFIX, iv.toString('base64'), tag.toString('base64'), ct.toString('base64')].join(':');
}

/** True if a stored value looks like something this module produced. */
function isEncrypted(value) {
    return typeof value === 'string' && value.startsWith(PREFIX + ':') && value.split(':').length === 4;
}

/**
 * Decrypt a stored password note.
 * Returns '' for genuinely empty values.
 * Returns null on a malformed blob, a wrong key, or a failed auth tag — never
 * throws, and never returns partial plaintext.
 */
function decrypt(stored) {
    if (stored === null || stored === undefined || stored === '') return '';
    if (!isEncrypted(stored)) return null;

    const key = vaultKey();
    if (!key) return null;

    try {
        const [, ivB64, tagB64, ctB64] = stored.split(':');
        const iv = Buffer.from(ivB64, 'base64');
        const tag = Buffer.from(tagB64, 'base64');
        const ct = Buffer.from(ctB64, 'base64');
        if (iv.length !== IV_LEN) return null;

        const decipher = crypto.createDecipheriv(ALGO, key, iv);
        decipher.setAuthTag(tag);
        return Buffer.concat([decipher.update(ct), decipher.final()]).toString('utf8');
    } catch {
        return null;
    }
}

module.exports = { encrypt, decrypt, isEncrypted, vaultAvailable };
