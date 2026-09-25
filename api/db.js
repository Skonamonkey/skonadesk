// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
const Database = require('better-sqlite3');
const path = require('path');
const bcrypt = require('bcryptjs');
const { v4: uuidv4 } = require('uuid');

const DB_PATH = process.env.DB_PATH || './skonadesk.db';

let db;

function getDb() {
    if (!db) {
        db = new Database(DB_PATH);
        db.pragma('journal_mode = WAL');
        db.pragma('foreign_keys = ON');
        initSchema();
        seedAdmin();
    }
    return db;
}

function initSchema() {
    db.exec(`
        CREATE TABLE IF NOT EXISTS users (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            username    TEXT    UNIQUE NOT NULL,
            password    TEXT    NOT NULL,
            email       TEXT,
            name        TEXT,
            is_admin    INTEGER DEFAULT 0,
            status      INTEGER DEFAULT 1,
            language    TEXT    DEFAULT '',
            created_at  TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS address_books (
            guid        TEXT    PRIMARY KEY,
            owner_id    INTEGER NOT NULL,
            name        TEXT    NOT NULL,
            created_at  TEXT    DEFAULT (datetime('now')),
            FOREIGN KEY (owner_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS ab_peers (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            ab_guid     TEXT    NOT NULL,
            peer_id     TEXT    NOT NULL,
            alias       TEXT    DEFAULT '',
            note        TEXT    DEFAULT '',
            password    TEXT    DEFAULT '',
            hash        TEXT    DEFAULT '',
            tags        TEXT    DEFAULT '[]',
            username    TEXT    DEFAULT '',
            hostname    TEXT    DEFAULT '',
            platform    TEXT    DEFAULT '',
            UNIQUE(ab_guid, peer_id),
            FOREIGN KEY (ab_guid) REFERENCES address_books(guid)
        );

        CREATE TABLE IF NOT EXISTS ab_tags (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            ab_guid     TEXT    NOT NULL,
            name        TEXT    NOT NULL,
            color       INTEGER DEFAULT 0,
            UNIQUE(ab_guid, name),
            FOREIGN KEY (ab_guid) REFERENCES address_books(guid)
        );

        CREATE TABLE IF NOT EXISTS device_groups (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    UNIQUE NOT NULL,
            created_at  TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS devices (
            peer_id     TEXT    PRIMARY KEY,
            user_id     INTEGER,
            group_id    INTEGER,
            uuid        TEXT,
            version     TEXT,
            os          TEXT,
            device_name TEXT,
            hostname    TEXT,
            username    TEXT,
            note        TEXT    DEFAULT '',
            last_seen   TEXT    DEFAULT (datetime('now')),
            active_conns TEXT   DEFAULT '[]',
            FOREIGN KEY (user_id)  REFERENCES users(id),
            FOREIGN KEY (group_id) REFERENCES device_groups(id)
        );

        CREATE TABLE IF NOT EXISTS audit_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type  TEXT    NOT NULL,
            peer_id     TEXT,
            conn_id     TEXT,
            user_id     INTEGER,
            remote_id   TEXT,
            conn_type   INTEGER,
            ip          TEXT,
            action      TEXT,
            note        TEXT,
            created_at  TEXT    DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS user_group_memberships (
            user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            group_id    INTEGER NOT NULL REFERENCES device_groups(id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, group_id)
        );

        CREATE TABLE IF NOT EXISTS device_group_assignments (
            peer_id     TEXT    NOT NULL,
            group_id    INTEGER NOT NULL,
            PRIMARY KEY (peer_id, group_id),
            FOREIGN KEY (group_id) REFERENCES device_groups(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    `);

    db.exec(`
        INSERT OR IGNORE INTO device_group_assignments (peer_id, group_id)
        SELECT peer_id, group_id FROM devices WHERE group_id IS NOT NULL
    `);

    // Seed default settings
    db.exec(`
        INSERT OR IGNORE INTO settings (key, value) VALUES ('default_language', 'en')
    `);

    const migrations = [
        "ALTER TABLE devices ADD COLUMN cpu       TEXT DEFAULT ''",
        "ALTER TABLE devices ADD COLUMN memory    TEXT DEFAULT ''",
        "ALTER TABLE devices ADD COLUMN wan_ip    TEXT DEFAULT ''",
        "ALTER TABLE audit_log ADD COLUMN conn_type INTEGER",
        "ALTER TABLE users ADD COLUMN language TEXT DEFAULT ''",
    ];
    for (const sql of migrations) {
        try { db.exec(sql); } catch (_) {}
    }

    encryptLegacyAbPasswords();
}

/**
 * One-way migration for passwords recorded against address-book entries before
 * they were encrypted at rest.
 *
 * Rows already matching the vault's `v1:` format are left alone, so this is
 * idempotent and safe to run on every boot. Anything else that is non-empty is
 * treated as a legacy plaintext value and encrypted.
 *
 * If no vault key is configured we do NOT touch the data: re-encrypting would be
 * impossible and we would rather leave rows as they are than destroy them. Storage
 * and reveal both fail closed elsewhere, so the values simply become unusable to
 * the API rather than being silently rewritten.
 */
function encryptLegacyAbPasswords() {
    const crypto = require('./crypto');
    if (!crypto.vaultAvailable()) {
        const pending = db.prepare(
            "SELECT COUNT(*) AS n FROM ab_peers WHERE password IS NOT NULL AND password != ''"
        ).get().n;
        if (pending > 0) {
            console.warn(
                `[db] ${pending} address-book password(s) present but no vault key configured ` +
                `(JWT_SECRET missing or default) — leaving them untouched.`
            );
        }
        return;
    }

    // ab_peers declares `id INTEGER PRIMARY KEY AUTOINCREMENT`, which in SQLite
    // makes `id` an alias for the rowid. Selecting the explicit column is the only
    // reliable way to address a row here — `SELECT rowid` returns it under the name
    // `id`, so keying off `rowid` silently matches nothing.
    const legacy = db.prepare(
        "SELECT id, password FROM ab_peers WHERE password IS NOT NULL AND password != ''"
    ).all().filter(r => !crypto.isEncrypted(r.password));

    if (legacy.length === 0) return;

    const upd = db.prepare('UPDATE ab_peers SET password = ? WHERE id = ?');
    const run = db.transaction(() => {
        for (const row of legacy) {
            const enc = crypto.encrypt(row.password);
            if (enc !== null) upd.run(enc, row.id);
        }
    });
    run();
    console.log(`[db] Encrypted ${legacy.length} legacy address-book password(s) at rest.`);
}

function seedAdmin() {
    const existing = db.prepare('SELECT id FROM users WHERE username = ?').get(process.env.ADMIN_USER || 'admin');
    if (!existing) {
        const hash = bcrypt.hashSync(process.env.ADMIN_PASS || 'changeme123', 10);
        const guid = uuidv4();
        db.prepare(`
            INSERT INTO users (username, password, name, is_admin, status)
            VALUES (?, ?, ?, 1, 1)
        `).run(process.env.ADMIN_USER || 'admin', hash, 'Administrator');

        const userId = db.prepare('SELECT id FROM users WHERE username = ?').get(process.env.ADMIN_USER || 'admin').id;
        db.prepare(`
            INSERT INTO address_books (guid, owner_id, name)
            VALUES (?, ?, 'My address book')
        `).run(guid, userId);

        console.log(`[db] Admin user created: ${process.env.ADMIN_USER || 'admin'}`);
    }
}

module.exports = { getDb };
