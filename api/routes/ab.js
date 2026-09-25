// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
const express = require('express');
const { v4: uuidv4 } = require('uuid');
const { getDb } = require('../db');
const { requireAuth } = require('../auth');

const router = express.Router();

function getUserPersonalAbGuid(userId) {
    const db = getDb();
    const ab = db.prepare('SELECT guid FROM address_books WHERE owner_id = ? LIMIT 1').get(userId);
    if (!ab) {
        const guid = uuidv4();
        db.prepare(`
            INSERT INTO address_books (guid, owner_id, name) VALUES (?, ?, 'My address book')
        `).run(guid, userId);
        return guid;
    }
    return ab.guid;
}

function fetchAbPeers(abGuid) {
    const db = getDb();
    return db.prepare('SELECT * FROM ab_peers WHERE ab_guid = ?').all(abGuid).map(peerRow);
}

function peerRow(p) {
    return {
        id:       p.peer_id,
        alias:    p.alias    || '',
        note:     p.note     || '',
        // The stored value is an encrypted blob and must never be handed to a
        // client. Clients receive only whether a recorded password exists;
        // reading it back requires the audited reveal endpoint.
        password: '',
        has_password: !!(p.password || ''),
        hash:     p.hash     || '',
        tags:     JSON.parse(p.tags || '[]'),
        username: p.username || '',
        hostname: p.hostname || '',
        platform: p.platform || '',
    };
}

function fetchAbTags(abGuid) {
    const db = getDb();
    return db.prepare('SELECT name, color FROM ab_tags WHERE ab_guid = ?').all(abGuid);
}

/**
 * Normalise a password value arriving from the RustDesk client before it is
 * written to ab_peers.
 *
 * Two cases matter:
 *  - The client pushes its whole address book back on every sync. Any password it
 *    sends is a local record, so it must be encrypted before it hits the database.
 *  - The client may send back a value it previously received from us. Encrypted
 *    blobs are passed through unchanged so a sync round-trip cannot double-encrypt
 *    or destroy an existing record.
 *
 * Returns '' for empty, or the value to store. Throws nothing; if encryption is
 * unavailable the password is dropped rather than stored in the clear.
 */
function normaliseInboundPassword(value) {
    const raw = typeof value === 'string' ? value : '';
    if (raw === '') return '';
    const crypto = require('../crypto');
    if (crypto.isEncrypted(raw)) return raw;
    if (!crypto.vaultAvailable()) return '';
    const enc = crypto.encrypt(raw);
    return enc === null ? '' : enc;
}

// ─── Legacy Address Book ──────────────────────────────────────────────────────

// GET /api/ab  — client fetches entire address book (legacy mode)
// Response: {"data": "<json-encoded-string>", "licensed_devices": 0}
// The inner data string is JSON of {peers:[...], tags:[...]}
router.get('/ab', requireAuth, (req, res) => {
    const guid = getUserPersonalAbGuid(req.user.id);
    const peers = fetchAbPeers(guid);
    const tags = fetchAbTags(guid).map(t => t.name);

    if (peers.length === 0 && tags.length === 0) {
        return res.json(null);
    }

    res.json({
        data: JSON.stringify({ peers, tags }),
        licensed_devices: 100,
    });
});

// POST /api/ab  — client pushes entire address book (legacy mode)
// Body: {"data": "<json-encoded-string>"}
router.post('/ab', requireAuth, (req, res) => {
    const db = getDb();
    const guid = getUserPersonalAbGuid(req.user.id);

    let abData;
    try {
        abData = JSON.parse(req.body.data || '{}');
    } catch {
        return res.status(400).json({ error: 'invalid data' });
    }

    const peers = Array.isArray(abData.peers) ? abData.peers : [];
    const tags  = Array.isArray(abData.tags)  ? abData.tags  : [];

    // The client pushes its whole address book back on every sync, and it only
    // ever knows the alias/note/tags fields — it is never given the stored
    // password blob. Preserve any password already recorded for an entry so a
    // routine sync cannot silently wipe credentials.
    const existingPasswords = new Map(
        db.prepare('SELECT peer_id, password FROM ab_peers WHERE ab_guid = ?').all(guid)
          .map(r => [r.peer_id, r.password || ''])
    );

    db.prepare('DELETE FROM ab_peers WHERE ab_guid = ?').run(guid);
    db.prepare('DELETE FROM ab_tags  WHERE ab_guid = ?').run(guid);

    const insertPeer = db.prepare(`
        INSERT OR IGNORE INTO ab_peers (ab_guid, peer_id, alias, note, password, tags)
        VALUES (?, ?, ?, ?, ?, ?)
    `);
    for (const p of peers) {
        const inbound = typeof p.password === 'string' ? p.password : '';
        // Only accept a password the client actually supplies; otherwise fall back
        // to whatever was already recorded for that entry.
        const pw = inbound === ''
            ? (existingPasswords.get(p.id || '') || '')
            : normaliseInboundPassword(inbound);
        insertPeer.run(guid, p.id || '', p.alias || '', p.note || '', pw, JSON.stringify(p.tags || []));
    }

    const insertTag = db.prepare('INSERT OR IGNORE INTO ab_tags (ab_guid, name, color) VALUES (?, ?, 0)');
    for (const t of tags) {
        if (typeof t === 'string') insertTag.run(guid, t);
    }

    res.send(null);
});

// ─── New-Mode Address Book ────────────────────────────────────────────────────

// POST /api/ab/settings — not fully supported, return 404 so client falls back to legacy
router.post('/ab/settings', requireAuth, (req, res) => {
    res.status(404).json({ error: 'shared address books not supported' });
});

// POST /api/ab/personal — returns the personal address book GUID for this user
router.post('/ab/personal', requireAuth, (req, res) => {
    const guid = getUserPersonalAbGuid(req.user.id);
    res.json({ guid });
});

// POST /api/ab/shared/profiles — returns list of shared address books (none for now)
router.post('/ab/shared/profiles', requireAuth, (req, res) => {
    res.json({ data: [] });
});

// GET or POST /api/ab/peers?current=N&pageSize=N&ab=GUID
// Response: {"data": [...], "total": N}
function handleAbPeers(req, res) {
    const db = getDb();
    const abGuid = req.query.ab || getUserPersonalAbGuid(req.user.id);
    const pageSize = parseInt(req.query.pageSize) || 100;
    const current  = parseInt(req.query.current)  || 1;
    const offset   = (current - 1) * pageSize;

    const total = db.prepare('SELECT COUNT(*) as n FROM ab_peers WHERE ab_guid = ?').get(abGuid).n;
    const rows  = db.prepare('SELECT * FROM ab_peers WHERE ab_guid = ? LIMIT ? OFFSET ?').all(abGuid, pageSize, offset);

    res.json({
        data: rows.map(peerRow),
        total,
    });
}

router.get('/ab/peers', requireAuth, handleAbPeers);
router.post('/ab/peers', requireAuth, handleAbPeers);

// GET or POST /api/ab/tags/{guid}  — returns tag list as a raw JSON array
// Response: [{name, color}, ...]
function handleAbTags(req, res) {
    const tags = fetchAbTags(req.params.guid);
    res.json(tags);
}

router.get('/ab/tags/:guid', requireAuth, handleAbTags);
router.post('/ab/tags/:guid', requireAuth, handleAbTags);

// POST /api/ab/peer/add/{guid}  — add a single peer
router.post('/ab/peer/add/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const { id, alias, note, password, hash, tags, username, hostname, platform } = req.body || {};
    if (!id) return res.status(400).json({ error: 'peer id required' });

    db.prepare(`
        INSERT OR REPLACE INTO ab_peers (ab_guid, peer_id, alias, note, password, hash, tags, username, hostname, platform)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(req.params.guid, id, alias || '', note || '', normaliseInboundPassword(password), hash || '', JSON.stringify(tags || []), username || '', hostname || '', platform || '');

    res.send('');
});

// PUT /api/ab/peer/update/{guid}  — update a single peer field
router.put('/ab/peer/update/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const body = req.body || {};
    const { id } = body;
    if (!id) return res.status(400).json({ error: 'peer id required' });

    const existing = db.prepare('SELECT * FROM ab_peers WHERE ab_guid=? AND peer_id=?').get(req.params.guid, id);
    if (!existing) return res.status(404).json({ error: 'peer not found' });

    const alias    = 'alias'    in body ? body.alias    : existing.alias;
    const note     = 'note'     in body ? body.note     : existing.note;
    const password = 'password' in body ? normaliseInboundPassword(body.password) : existing.password;
    const hash     = 'hash'     in body ? body.hash     : existing.hash;
    const tags     = 'tags'     in body ? JSON.stringify(body.tags) : existing.tags;
    const username = 'username' in body ? body.username : existing.username;
    const hostname = 'hostname' in body ? body.hostname : existing.hostname;
    const platform = 'platform' in body ? body.platform : existing.platform;

    db.prepare(`
        UPDATE ab_peers SET alias=?, note=?, password=?, hash=?, tags=?, username=?, hostname=?, platform=?
        WHERE ab_guid=? AND peer_id=?
    `).run(alias || '', note || '', password || '', hash || '', tags || '[]', username || '', hostname || '', platform || '', req.params.guid, id);

    res.send('');
});

// DELETE /api/ab/peer/{guid}  — body is JSON array of peer IDs ["id1","id2"]
router.delete('/ab/peer/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const ids = Array.isArray(req.body) ? req.body : [];

    const del = db.prepare('DELETE FROM ab_peers WHERE ab_guid=? AND peer_id=?');
    for (const id of ids) {
        del.run(req.params.guid, id);
    }

    res.send('');
});

// POST /api/ab/tag/add/{guid}  — add a tag
router.post('/ab/tag/add/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const { name, color } = req.body || {};
    if (!name) return res.status(400).json({ error: 'tag name required' });

    db.prepare('INSERT OR IGNORE INTO ab_tags (ab_guid, name, color) VALUES (?, ?, ?)').run(
        req.params.guid, name, color || 0
    );
    res.send('');
});

// PUT /api/ab/tag/rename/{guid}  — body {"old": "oldname", "new": "newname"}
router.put('/ab/tag/rename/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const oldName = req.body?.old;
    const newName = req.body?.new;
    if (!oldName || !newName) return res.status(400).json({ error: 'old and new required' });

    db.prepare('UPDATE ab_tags SET name=? WHERE ab_guid=? AND name=?').run(newName, req.params.guid, oldName);
    res.send('');
});

// PUT /api/ab/tag/update/{guid}  — update tag color; body {"name": "tag", "color": 123456}
router.put('/ab/tag/update/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const { name, color } = req.body || {};
    if (!name) return res.status(400).json({ error: 'tag name required' });

    db.prepare('UPDATE ab_tags SET color=? WHERE ab_guid=? AND name=?').run(color || 0, req.params.guid, name);
    res.send('');
});

// DELETE /api/ab/tag/{guid}  — body is JSON array of tag names ["tag1"]
router.delete('/ab/tag/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const names = Array.isArray(req.body) ? req.body : [];

    const del = db.prepare('DELETE FROM ab_tags WHERE ab_guid=? AND name=?');
    for (const name of names) {
        del.run(req.params.guid, name);
    }

    res.send('');
});

// ─── Recorded entry passwords ─────────────────────────────────────────────────
//
// These endpoints manage the manually-entered password note stored against an
// address-book entry. This is NOT RustDesk's saved connection password, which the
// client keeps locally and never sends to the server.

/**
 * Authorise access to an address book and confirm the peer exists.
 * Access is granted to the book's owner, or to an admin acting on any book.
 */
function authorisePeer(db, req, res) {
    const book = db.prepare('SELECT guid, owner_id FROM address_books WHERE guid = ?').get(req.params.guid);
    if (!book) {
        res.status(404).json({ error: 'Address book not found' });
        return null;
    }
    const isOwner = book.owner_id === req.user.id;
    if (!isOwner && !req.user.is_admin) {
        res.status(403).json({ error: 'Not permitted for this address book' });
        return null;
    }
    const peer = db.prepare('SELECT * FROM ab_peers WHERE ab_guid = ? AND peer_id = ?')
        .get(req.params.guid, req.params.peerId);
    if (!peer) {
        res.status(404).json({ error: 'Entry not found' });
        return null;
    }
    return { book, peer, isOwner };
}

// PUT /api/ab/peer/password/{guid}/{peerId}  — set or clear the recorded password
// Body: { password: "..." }  — empty string clears it
router.put('/ab/peer/password/:guid/:peerId', requireAuth, (req, res) => {
    const db = getDb();
    const ctx = authorisePeer(db, req, res);
    if (!ctx) return;

    const { password } = req.body || {};
    if (typeof password !== 'string') {
        return res.status(400).json({ error: 'password must be a string' });
    }

    const crypto = require('../crypto');

    if (password === '') {
        db.prepare('UPDATE ab_peers SET password = ? WHERE ab_guid = ? AND peer_id = ?')
          .run('', req.params.guid, req.params.peerId);
        return res.json({ data: 'ok', cleared: true });
    }

    const enc = crypto.encrypt(password);
    if (enc === null) {
        // Fail closed: never persist a credential we cannot encrypt.
        return res.status(503).json({
            error: 'Password storage is unavailable: no usable vault key configured on the server.',
        });
    }

    db.prepare('UPDATE ab_peers SET password = ? WHERE ab_guid = ? AND peer_id = ?')
      .run(enc, req.params.guid, req.params.peerId);

    res.json({ data: 'ok', stored: true });
});

// POST /api/ab/peer/reveal/{guid}/{peerId}  — decrypt and return the password
router.post('/ab/peer/reveal/:guid/:peerId', requireAuth, (req, res) => {
    const db = getDb();
    const ctx = authorisePeer(db, req, res);
    if (!ctx) return;

    const crypto = require('../crypto');

    if (!crypto.vaultAvailable()) {
        return res.status(503).json({ error: 'Password storage is unavailable: no usable vault key configured on the server.' });
    }

    const stored = ctx.peer.password || '';
    if (stored === '') {
        return res.json({ data: 'empty', password: null });
    }

    const plaintext = crypto.decrypt(stored);
    if (plaintext === null) {
        return res.status(422).json({ error: 'Stored password could not be decrypted (wrong key or corrupted record).' });
    }

    // Every successful reveal is recorded. Address-book entry IDs can identify a
    // machine, so this is deliberately audited even though it is a read.
    db.prepare(`
        INSERT INTO audit_log (event_type, peer_id, user_id, action, note)
        VALUES ('ab_password_view', ?, ?, ?, ?)
    `).run(
        ctx.peer.peer_id,
        req.user.id,
        'reveal',
        `${req.user.username} revealed entry password (${ctx.isOwner ? 'owner' : 'admin'})`
    );

    res.json({ data: 'ok', password: plaintext });
});

module.exports = router;
