// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
const express = require('express');
const bcrypt = require('bcryptjs');
const { v4: uuidv4 } = require('uuid');
const { getDb } = require('../db');
const { requireAuth } = require('../auth');

const router = express.Router();

function requireAdmin(req, res, next) {
    if (!req.user?.is_admin) return res.status(403).json({ error: 'Admin required' });
    next();
}

// ── Stats ──────────────────────────────────────────────────────────────────

router.get('/stats', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const totalDevices  = db.prepare('SELECT COUNT(*) as n FROM devices').get().n;
    const onlineDevices = db.prepare(
        "SELECT COUNT(*) as n FROM devices WHERE last_seen >= datetime('now', '-2 minutes')"
    ).get().n;
    const totalUsers    = db.prepare('SELECT COUNT(*) as n FROM users').get().n;
    const connsToday    = db.prepare(
        "SELECT COUNT(*) as n FROM audit_log WHERE event_type = 'conn' AND created_at >= datetime('now', '-1 day')"
    ).get().n;

    const activeRows = db.prepare(
        "SELECT active_conns FROM devices WHERE active_conns IS NOT NULL AND active_conns != '[]' AND last_seen >= datetime('now', '-2 minutes')"
    ).all();
    const activeSessions = activeRows.reduce((sum, r) => {
        try { return sum + (JSON.parse(r.active_conns) || []).length; } catch { return sum; }
    }, 0);

    res.json({
        total_devices:       totalDevices,
        online_devices:      onlineDevices,
        total_users:         totalUsers,
        connections_today:   connsToday,
        active_sessions:     activeSessions,
    });
});

// ── Users ──────────────────────────────────────────────────────────────────

function handleUsers(req, res) {
    const db = getDb();
    const params   = { ...req.query, ...req.body };
    const page     = parseInt(params.current)  || 1;
    const limit    = parseInt(params.pageSize) || parseInt(params.total) || 10;
    const offset   = (page - 1) * limit;

    const count = db.prepare('SELECT COUNT(*) as n FROM users').get().n;
    const users = db.prepare(`
        SELECT id, username, name, email, is_admin, status, created_at
        FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?
    `).all(limit, offset);

    res.json({
        data: users.map(u => ({
            id:           u.id,
            name:         u.username,
            display_name: u.name || u.username,
            email:        u.email || '',
            note:         '',
            status:       u.status,
            is_admin:     u.is_admin === 1,
        })),
        total: count,
    });
}

router.get('/users',  requireAuth, handleUsers);
router.post('/users', requireAuth, handleUsers);

router.post('/users/add', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { username, password, name, email, is_admin } = req.body || {};
    if (!username || !password) return res.status(400).json({ error: 'username and password required' });
    if (db.prepare('SELECT id FROM users WHERE username = ?').get(username)) {
        return res.status(409).json({ error: 'Username already exists' });
    }
    const hash = bcrypt.hashSync(password, 10);
    const guid = uuidv4();
    db.prepare(`
        INSERT INTO users (username, password, name, email, is_admin, status)
        VALUES (?, ?, ?, ?, ?, 1)
    `).run(username, hash, name || username, email || '', is_admin ? 1 : 0);
    const userId = db.prepare('SELECT id FROM users WHERE username = ?').get(username).id;
    db.prepare('INSERT INTO address_books (guid, owner_id, name) VALUES (?, ?, ?)').run(guid, userId, 'My address book');
    res.json({ data: 'ok' });
});

router.put('/users/:username', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { username } = req.params;
    const { name, email, password, is_admin, status } = req.body || {};
    const user = db.prepare('SELECT id FROM users WHERE username = ?').get(username);
    if (!user) return res.status(404).json({ error: 'User not found' });
    if (password) {
        db.prepare('UPDATE users SET password = ? WHERE username = ?').run(bcrypt.hashSync(password, 10), username);
    }
    if (name     !== undefined) db.prepare('UPDATE users SET name     = ? WHERE username = ?').run(name, username);
    if (email    !== undefined) db.prepare('UPDATE users SET email    = ? WHERE username = ?').run(email, username);
    if (is_admin !== undefined) db.prepare('UPDATE users SET is_admin = ? WHERE username = ?').run(is_admin ? 1 : 0, username);
    if (status   !== undefined) db.prepare('UPDATE users SET status   = ? WHERE username = ?').run(status ? 1 : 0, username);
    res.json({ data: 'ok' });
});

router.delete('/users/:username', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { username } = req.params;
    if (username === req.user.username) return res.status(400).json({ error: 'Cannot delete yourself' });
    const user = db.prepare('SELECT id FROM users WHERE username = ?').get(username);
    if (!user) return res.status(404).json({ error: 'User not found' });
    db.prepare('DELETE FROM users WHERE username = ?').run(username);
    res.json({ data: 'ok' });
});

// ── Peers / Devices ────────────────────────────────────────────────────────

function attachGroups(db, devices) {
    if (!devices.length) return devices;
    const placeholders = devices.map(() => '?').join(',');
    const peerIds = devices.map(d => d.peer_id);
    const rows = db.prepare(`
        SELECT dga.peer_id, dga.group_id, dg.name
        FROM device_group_assignments dga
        JOIN device_groups dg ON dga.group_id = dg.id
        WHERE dga.peer_id IN (${placeholders})
        ORDER BY dg.name
    `).all(...peerIds);
    const byPeer = {};
    for (const r of rows) {
        if (!byPeer[r.peer_id]) byPeer[r.peer_id] = [];
        byPeer[r.peer_id].push({ id: r.group_id, name: r.name });
    }
    return devices.map(d => {
        const groups = byPeer[d.peer_id] || [];
        return {
            id:                d.peer_id,
            info: {
                os:          d.os          || '',
                device_name: d.device_name || '',
                username:    d.username    || '',
                hostname:    d.hostname    || '',
                cpu:         d.cpu         || '',
                memory:      d.memory      || '',
                wan_ip:      d.wan_ip      || '',
            },
            status:            1,
            user:              d.user_name || '',
            user_name:         d.user_name || '',
            groups,
            group_id:          groups[0]?.id   || null,
            device_group_name: groups.map(g => g.name).join(', '),
            note:              d.note      || '',
            last_seen:         d.last_seen || '',
            version:           d.version   || '',
        };
    });
}

function handlePeers(req, res) {
    const db = getDb();
    const params = { ...req.query, ...req.body };
    const page   = parseInt(params.current)  || 1;
    const limit  = parseInt(params.pageSize) || parseInt(params.total) || 20;
    const offset = (page - 1) * limit;

    const filterGroupId = parseInt(params.group_id) || null;
    const groupFilter   = filterGroupId
        ? 'AND EXISTS (SELECT 1 FROM device_group_assignments WHERE peer_id = d.peer_id AND group_id = ?)'
        : '';
    const groupArgs = filterGroupId ? [filterGroupId] : [];

    if (req.user.is_admin) {
        const count   = db.prepare(`SELECT COUNT(*) as n FROM devices d WHERE 1=1 ${groupFilter}`).get(...groupArgs).n;
        const devices = db.prepare(`
            SELECT d.*, u.username as user_name
            FROM devices d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE 1=1 ${groupFilter}
            ORDER BY d.last_seen DESC LIMIT ? OFFSET ?
        `).all(...groupArgs, limit, offset);
        return res.json({ data: attachGroups(db, devices), total: count });
    }

    const uid = req.user.id;
    const visibilitySql = `
        (
            NOT EXISTS (SELECT 1 FROM device_group_assignments WHERE peer_id = d.peer_id)
            OR d.user_id = ?
            OR EXISTS (
                SELECT 1 FROM device_group_assignments dga
                JOIN user_group_memberships ugm ON dga.group_id = ugm.group_id
                WHERE dga.peer_id = d.peer_id AND ugm.user_id = ?
            )
        )
        ${groupFilter}
    `;
    const count = db.prepare(`SELECT COUNT(*) as n FROM devices d WHERE ${visibilitySql}`).get(uid, uid, ...groupArgs).n;
    const devices = db.prepare(`
        SELECT d.*, u.username as user_name
        FROM devices d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE ${visibilitySql}
        ORDER BY d.last_seen DESC LIMIT ? OFFSET ?
    `).all(uid, uid, ...groupArgs, limit, offset);
    res.json({ data: attachGroups(db, devices), total: count });
}

router.get('/peers',  requireAuth, handlePeers);
router.post('/peers', requireAuth, handlePeers);

router.put('/peers/:id', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { id } = req.params;
    const { group_ids, note, user_id } = req.body || {};
    if (!db.prepare('SELECT peer_id FROM devices WHERE peer_id = ?').get(id)) {
        return res.status(404).json({ error: 'Device not found' });
    }
    if (group_ids !== undefined) {
        db.prepare('DELETE FROM device_group_assignments WHERE peer_id = ?').run(id);
        const ids = (Array.isArray(group_ids) ? group_ids : [group_ids]).filter(Boolean);
        const insert = db.prepare('INSERT OR IGNORE INTO device_group_assignments (peer_id, group_id) VALUES (?, ?)');
        for (const gid of ids) insert.run(id, parseInt(gid));
        db.prepare('UPDATE devices SET group_id = ? WHERE peer_id = ?').run(ids[0] ? parseInt(ids[0]) : null, id);
    }
    if (note    !== undefined) db.prepare('UPDATE devices SET note    = ? WHERE peer_id = ?').run(note, id);
    if (user_id !== undefined) db.prepare('UPDATE devices SET user_id = ? WHERE peer_id = ?').run(user_id || null, id);
    res.json({ data: 'ok' });
});

router.delete('/peers/:id', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    db.prepare('DELETE FROM devices WHERE peer_id = ?').run(req.params.id);
    res.json({ data: 'ok' });
});

// ── Device Groups ──────────────────────────────────────────────────────────

function handleDeviceGroupAccessible(req, res) {
    const db = getDb();
    const params = { ...req.query };
    const page   = parseInt(params.current)  || 1;
    const limit  = parseInt(params.pageSize) || 100;
    const offset = (page - 1) * limit;

    if (req.user.is_admin) {
        const count  = db.prepare('SELECT COUNT(*) as n FROM device_groups').get().n;
        const groups = db.prepare('SELECT id, name FROM device_groups ORDER BY name LIMIT ? OFFSET ?').all(limit, offset);
        return res.json({ data: groups.map(g => ({ id: g.id, name: g.name })), total: count });
    }

    const uid = req.user.id;
    const count  = db.prepare('SELECT COUNT(*) as n FROM device_groups dg JOIN user_group_memberships ugm ON dg.id = ugm.group_id WHERE ugm.user_id = ?').get(uid).n;
    const groups = db.prepare('SELECT dg.id, dg.name FROM device_groups dg JOIN user_group_memberships ugm ON dg.id = ugm.group_id WHERE ugm.user_id = ? ORDER BY dg.name LIMIT ? OFFSET ?').all(uid, limit, offset);
    res.json({ data: groups.map(g => ({ id: g.id, name: g.name })), total: count });
}

router.get('/device-group/accessible',  requireAuth, handleDeviceGroupAccessible);
router.post('/device-group/accessible', requireAuth, handleDeviceGroupAccessible);

router.post('/device-group/add', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { name } = req.body || {};
    if (!name) return res.status(400).json({ error: 'name required' });
    if (db.prepare('SELECT id FROM device_groups WHERE name = ?').get(name)) {
        return res.status(409).json({ error: 'Group already exists' });
    }
    const info = db.prepare('INSERT INTO device_groups (name) VALUES (?)').run(name);
    res.json({ data: 'ok', id: info.lastInsertRowid });
});

router.put('/device-group/:id', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { name } = req.body || {};
    if (!name) return res.status(400).json({ error: 'name required' });
    db.prepare('UPDATE device_groups SET name = ? WHERE id = ?').run(name, req.params.id);
    res.json({ data: 'ok' });
});

router.delete('/device-group/:id', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    db.prepare('UPDATE devices SET group_id = NULL WHERE group_id = ?').run(req.params.id);
    db.prepare('DELETE FROM device_group_assignments WHERE group_id = ?').run(req.params.id);
    db.prepare('DELETE FROM device_groups WHERE id = ?').run(req.params.id);
    res.json({ data: 'ok' });
});

// ── Group Memberships ──────────────────────────────────────────────────────

router.get('/group/memberships', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const rows = db.prepare(`
        SELECT ugm.group_id, ugm.user_id, u.username, u.name
        FROM user_group_memberships ugm
        JOIN users u ON u.id = ugm.user_id
        ORDER BY ugm.group_id, u.username
    `).all();
    res.json({ data: rows });
});

router.get('/group/:id/members', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const members = db.prepare(`
        SELECT u.id, u.username, u.name
        FROM users u
        JOIN user_group_memberships ugm ON u.id = ugm.user_id
        WHERE ugm.group_id = ?
        ORDER BY u.username
    `).all(req.params.id);
    res.json({ data: members });
});

router.post('/group/:id/member', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { user_id } = req.body || {};
    if (!user_id) return res.status(400).json({ error: 'user_id required' });
    db.prepare('INSERT OR IGNORE INTO user_group_memberships (user_id, group_id) VALUES (?, ?)').run(user_id, req.params.id);
    res.json({ data: 'ok' });
});

router.delete('/group/:id/member/:userId', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    db.prepare('DELETE FROM user_group_memberships WHERE group_id = ? AND user_id = ?').run(req.params.id, req.params.userId);
    res.json({ data: 'ok' });
});

// ── Active Sessions ────────────────────────────────────────────────────────

router.get('/sessions', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();

    const activeDevices = db.prepare(`
        SELECT d.peer_id, d.device_name, d.hostname, d.os, d.active_conns, d.last_seen,
               u.username as user_name
        FROM devices d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.active_conns IS NOT NULL
          AND d.active_conns != '[]'
          AND d.last_seen >= datetime('now', '-2 minutes')
        ORDER BY d.last_seen DESC
    `).all();

    const sessions = [];
    const deviceLookup = db.prepare('SELECT peer_id, device_name, hostname, os, wan_ip FROM devices WHERE peer_id = ?');

    for (const dev of activeDevices) {
        let connIds = [];
        try { connIds = JSON.parse(dev.active_conns) || []; } catch {}
        for (const connId of connIds) {
            const connIdStr  = String(connId);
            const connIdInt  = parseInt(connId, 10);

            const callerDirect = !isNaN(connIdInt) && connIdStr !== dev.peer_id
                ? deviceLookup.get(connIdStr)
                : null;

            const auditRow = !callerDirect
                ? (isNaN(connIdInt)
                    ? db.prepare(`
                        SELECT remote_id, ip, created_at, conn_type FROM audit_log
                        WHERE event_type = 'conn' AND peer_id = ? AND action = 'new'
                        ORDER BY created_at DESC LIMIT 1
                      `).get(dev.peer_id)
                    : db.prepare(`
                        SELECT remote_id, ip, created_at, conn_type FROM audit_log
                        WHERE event_type = 'conn' AND peer_id = ? AND action = 'new'
                          AND (conn_id = ? OR CAST(conn_id AS INTEGER) = ?)
                        ORDER BY created_at DESC LIMIT 1
                      `).get(dev.peer_id, connIdInt, connIdInt))
                : null;

            const connIp = auditRow?.ip || '';

            const callerByPeerId = (!callerDirect && auditRow?.remote_id && auditRow.remote_id !== dev.peer_id)
                ? deviceLookup.get(auditRow.remote_id)
                : null;

            const callerByIp = (!callerDirect && !callerByPeerId && connIp)
                ? db.prepare(`
                    SELECT peer_id, device_name, hostname, os, wan_ip FROM devices
                    WHERE wan_ip = ? AND peer_id != ? AND last_seen >= datetime('now', '-10 minutes')
                    ORDER BY last_seen DESC LIMIT 1
                  `).get(connIp, dev.peer_id)
                : null;

            const caller = callerDirect || callerByPeerId || callerByIp;

            sessions.push({
                target_id:       dev.peer_id,
                target_name:     dev.device_name || dev.hostname || dev.peer_id,
                target_os:       dev.os || '',
                target_user:     dev.user_name || '',
                caller_id:       caller?.peer_id || '',
                caller_name:     caller ? (caller.device_name || caller.hostname || caller.peer_id) : '',
                caller_os:       caller?.os || '',
                caller_wan_ip:   connIp || caller?.wan_ip || '',
                conn_type:       auditRow?.conn_type ?? null,
                connected_since: auditRow?.created_at || null,
                last_seen:       dev.last_seen,
            });
        }
    }

    res.json({ data: sessions, total: sessions.length });
});

// ── Passthrough stubs ──────────────────────────────────────────────────────

router.post('/devices/cli',    requireAuth, (req, res) => res.json({ data: 'success' }));
router.post('/devices/deploy', requireAuth, (req, res) => res.json({ data: 'success' }));

// ── Address Book (admin view) ──────────────────────────────────────────────
// GET /api/ab/admin?user_id=N  — admin endpoint returning any user's address book

router.get('/ab/admin', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const userId = parseInt(req.query.user_id) || null;

    let abRow;
    if (userId) {
        abRow = db.prepare('SELECT * FROM address_books WHERE owner_id = ?').get(userId);
    }

    if (!abRow) {
        return res.json({ guid: null, peers: [], tags: [] });
    }

    const peers = db.prepare('SELECT * FROM ab_peers WHERE ab_guid = ?').all(abRow.guid).map(p => ({
        id:       p.peer_id,
        alias:    p.alias    || '',
        note:     p.note     || '',
        tags:     JSON.parse(p.tags || '[]'),
        username: p.username || '',
        hostname: p.hostname || '',
        platform: p.platform || '',
    }));

    const tags = db.prepare('SELECT name, color FROM ab_tags WHERE ab_guid = ?').all(abRow.guid);

    res.json({ guid: abRow.guid, peers, tags });
});

// POST /api/ab/copy  — admin-only: copy one user's address book into another
// Body: { from_user_id, to_user_id, mode, copy_passwords }
//   mode: 'merge' (default) | 'replace'
//   copy_passwords: false (default) — the ab_peers.password column holds any
//     password an admin has manually recorded against the peer in the dashboard.
//     RustDesk's own client-side saved password lives in the client's
//     ~/.config/rustdesk/peers/<id>.toml and is never visible to the server, so
//     it cannot be transferred either way. Off by default: moving credentials
//     between accounts should be deliberate, not a side effect of copying.
router.post('/ab/copy', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { from_user_id, to_user_id } = req.body || {};
    const mode = (req.body?.mode === 'replace') ? 'replace' : 'merge';
    const copyPasswords = req.body?.copy_passwords === true;

    const fromId = parseInt(from_user_id);
    const toId   = parseInt(to_user_id);

    if (!fromId || !toId) return res.status(400).json({ error: 'from_user_id and to_user_id required' });
    if (fromId === toId)  return res.status(400).json({ error: 'Source and target user must differ' });

    const fromUser = db.prepare('SELECT id, username FROM users WHERE id = ?').get(fromId);
    const toUser   = db.prepare('SELECT id, username FROM users WHERE id = ?').get(toId);
    if (!fromUser) return res.status(404).json({ error: 'Source user not found' });
    if (!toUser)   return res.status(404).json({ error: 'Target user not found' });

    // Resolve (or lazily create) both address books, same rule as getUserPersonalAbGuid
    function ensureAb(userId) {
        const ab = db.prepare('SELECT guid FROM address_books WHERE owner_id = ? LIMIT 1').get(userId);
        if (ab) return ab.guid;
        const guid = uuidv4();
        db.prepare("INSERT INTO address_books (guid, owner_id, name) VALUES (?, ?, 'My address book')")
          .run(guid, userId);
        return guid;
    }

    const fromGuid = ensureAb(fromId);
    const toGuid   = ensureAb(toId);

    if (fromGuid === toGuid) {
        return res.status(400).json({ error: 'Source and target share an address book' });
    }

    const srcPeers = db.prepare('SELECT * FROM ab_peers WHERE ab_guid = ?').all(fromGuid);
    const srcTags  = db.prepare('SELECT name, color FROM ab_tags WHERE ab_guid = ?').all(fromGuid);

    const copy = db.transaction(() => {
        if (mode === 'replace') {
            db.prepare('DELETE FROM ab_peers WHERE ab_guid = ?').run(toGuid);
            db.prepare('DELETE FROM ab_tags  WHERE ab_guid = ?').run(toGuid);
        }

        const insPeer = db.prepare(`
            INSERT INTO ab_peers (ab_guid, peer_id, alias, note, password, hash, tags, username, hostname, platform)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(ab_guid, peer_id) DO UPDATE SET
                alias    = excluded.alias,
                note     = excluded.note,
                hash     = excluded.hash,
                tags     = excluded.tags,
                username = excluded.username,
                hostname = excluded.hostname,
                platform = excluded.platform
        `);
        const setPassword = db.prepare(
            'UPDATE ab_peers SET password = ? WHERE ab_guid = ? AND peer_id = ?'
        );
        for (const p of srcPeers) {
            // password is never written by the upsert: on INSERT it seeds empty, and
            // on conflict it is left alone. Only an explicit copy_passwords sets it,
            // so a credentials-blind copy can never leak or clobber a password.
            insPeer.run(
                toGuid, p.peer_id, p.alias || '', p.note || '', '',
                p.hash || '', p.tags || '[]', p.username || '', p.hostname || '', p.platform || ''
            );
            if (copyPasswords && (p.password || '')) {
                setPassword.run(p.password, toGuid, p.peer_id);
            }
        }

        const insTag = db.prepare(
            'INSERT OR IGNORE INTO ab_tags (ab_guid, name, color) VALUES (?, ?, ?)'
        );
        for (const t of srcTags) {
            insTag.run(toGuid, t.name, t.color || 0);
        }
    });

    copy();

    const peersCopied = srcPeers.length;
    const tagsCopied  = srcTags.length;

    db.prepare(`
        INSERT INTO audit_log (event_type, user_id, action, note)
        VALUES ('ab_copy', ?, ?, ?)
    `).run(
        req.user.id,
        `${fromUser.username} -> ${toUser.username}`,
        `copied ${peersCopied} peers / ${tagsCopied} tags (${mode}${copyPasswords ? ', incl. passwords' : ', passwords excluded'})`
    );

    res.json({
        data: 'ok',
        peers_copied: peersCopied,
        tags_copied:  tagsCopied,
        mode,
        password_copy: copyPasswords,
        from: fromUser.username,
        to:   toUser.username,
    });
});

// ── Settings ────────────────────────────────────────────────────────────────

router.get('/settings', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const rows = db.prepare('SELECT key, value FROM settings').all();
    const settings = {};
    for (const row of rows) {
        settings[row.key] = row.value;
    }
    res.json({ data: settings });
});

router.put('/settings', requireAuth, requireAdmin, (req, res) => {
    const db = getDb();
    const { default_language } = req.body || {};
    if (default_language !== undefined) {
        db.prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)').run('default_language', default_language);
    }
    res.json({ data: 'ok' });
});

module.exports = router;
