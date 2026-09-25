<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
$user = current_user();

$flash     = '';
$flashType = 'success';

$viewUserId = $user['is_admin'] ? (int)($_GET['user_id'] ?? $user['id']) : $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $abGuid  = $_POST['ab_guid'] ?? '';

    if ($action === 'add_peer' && $abGuid) {
        $peerId = trim($_POST['peer_id'] ?? '');
        $alias  = trim($_POST['alias']   ?? '');
        $note   = trim($_POST['note']    ?? '');
        if ($peerId) {
            $resp = api_post("/ab/peer/add/$abGuid", [
                'id'    => $peerId,
                'alias' => $alias,
                'note'  => $note,
                'tags'  => [],
            ]);
            $flash = api_ok($resp) ? __('addressbook.peer_added') : ("Failed: " . ($resp['error'] ?? 'unknown'));
            if (!api_ok($resp)) $flashType = 'danger';
        } else {
            $flash = __('addressbook.device_id_required');
            $flashType = 'danger';
        }

    } elseif ($action === 'delete_peer' && $abGuid) {
        $peerId = $_POST['peer_id'] ?? '';
        $resp   = api_request('DELETE', "/ab/peer/$abGuid", [$peerId]);
        $flash  = api_ok($resp) ? __('addressbook.peer_removed') : ("Failed: " . ($resp['error'] ?? 'unknown'));
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'add_tag' && $abGuid) {
        $tagName = trim($_POST['tag_name'] ?? '');
        if ($tagName) {
            $resp  = api_post("/ab/tag/add/$abGuid", ['name' => $tagName, 'color' => 0]);
            $flash = api_ok($resp) ? __('addressbook.tag_added') : ("Failed: " . ($resp['error'] ?? 'unknown'));
            if (!api_ok($resp)) $flashType = 'danger';
        }

    } elseif ($action === 'delete_tag' && $abGuid) {
        $tagName = $_POST['tag_name'] ?? '';
        $resp    = api_request('DELETE', "/ab/tag/$abGuid", [$tagName]);
        $flash   = api_ok($resp) ? __('addressbook.tag_removed') : ("Failed: " . ($resp['error'] ?? 'unknown'));
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'set_password' && $abGuid) {
        $peerId = $_POST['peer_id'] ?? '';
        $pw     = (string)($_POST['peer_password'] ?? '');
        if ($peerId) {
            $resp = api_put("/ab/peer/password/$abGuid/" . rawurlencode($peerId), ['password' => $pw]);
            if (api_ok($resp)) {
                $flash = $pw === '' ? __('addressbook.password_cleared') : __('addressbook.password_saved');
            } else {
                $flash = __('addressbook.password_failed') . ' ' . ($resp['error'] ?? 'unknown');
                $flashType = 'danger';
            }
        }

    } elseif ($action === 'copy_ab' && $user['is_admin']) {
        $fromId = (int)($_POST['copy_from'] ?? 0);
        $toId   = (int)($_POST['copy_to']   ?? 0);
        $mode   = ($_POST['copy_mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';

        if (!$fromId || !$toId) {
            $flash = __('addressbook.copy_need_users');
            $flashType = 'danger';
        } elseif ($fromId === $toId) {
            $flash = __('addressbook.copy_same_user');
            $flashType = 'danger';
        } else {
            $resp = api_post('/ab/copy', [
                'from_user_id'    => $fromId,
                'to_user_id'      => $toId,
                'mode'            => $mode,
                'copy_passwords'  => !empty($_POST['copy_passwords']),
            ]);
            if (api_ok($resp)) {
                $flash = __('addressbook.copy_done',
                    (int)($resp['peers_copied'] ?? 0),
                    (int)($resp['tags_copied']  ?? 0));
                if (!empty($resp['password_copy'])) {
                    $flash .= ' ' . __('addressbook.copy_done_passwords');
                }
            } else {
                $flash = __('addressbook.copy_failed') . ' ' . ($resp['error'] ?? 'unknown');
                $flashType = 'danger';
            }
        }
    }

    $params = [];
    if ($user['is_admin'] && $viewUserId !== $user['id']) $params[] = "user_id=$viewUserId";
    if ($flash) $params[] = "notice=" . urlencode($flash);
    $qs = $params ? ('?' . implode('&', $params)) : '';
    header("Location: /addressbook.php$qs");
    exit;
}

$abData  = [];
$abGuid  = '';
$peers   = [];
$tags    = [];

if ($user['is_admin'] && $viewUserId !== $user['id']) {
    $abData = api_get('/ab/admin', ['user_id' => $viewUserId]);
    $abGuid = $abData['guid'] ?? '';
    $peers  = $abData['peers'] ?? [];
    $tags   = $abData['tags']  ?? [];
} else {
    $abData = api_post('/ab/personal');
    $abGuid = $abData['guid'] ?? '';
    if ($abGuid) {
        $peersResp = api_get('/ab/peers', ['ab' => $abGuid, 'pageSize' => 200, 'current' => 1]);
        $peers     = $peersResp['data'] ?? [];
        $tagsResp  = api_get("/ab/tags/$abGuid");
        $tags      = is_array($tagsResp) ? $tagsResp : [];
    }
}

$userList = [];
if ($user['is_admin']) {
    $ur = api_get('/users', ['current' => 1, 'pageSize' => 200]);
    $userList = $ur['data'] ?? [];
}

$allDevicesResp = api_get('/peers', ['current' => 1, 'pageSize' => 500]);
$allDevices = $allDevicesResp['data'] ?? [];

page_open(__('addressbook.title'));
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?>" style="margin:0 0 20px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="section-header" style="margin-bottom:20px">
  <h2><?= __('addressbook.title') ?></h2>

  <?php if ($user['is_admin'] && !empty($userList)): ?>
  <form method="GET" style="display:flex;align-items:center;gap:8px">
    <label for="userSel" style="font-size:var(--font-sm);color:var(--text-muted);white-space:nowrap"><?= __('addressbook.viewing') ?></label>
    <div style="min-width:180px">
      <select id="userSel" name="user_id" onchange="this.form.submit()">
        <option value="<?= $user['id'] ?>"<?= $viewUserId === $user['id'] ? ' selected' : '' ?>><?= __('addressbook.my_book') ?></option>
        <?php foreach ($userList as $u):
            if ($u['name'] === $user['username']) continue; ?>
        <option value="<?= $u['id'] ?>"<?= $viewUserId === (int)$u['id'] ? ' selected' : '' ?>>
          <?= htmlspecialchars($u['display_name'] ?: $u['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
  <?php endif; ?>

  <?php if ($abGuid): ?>
  <button class="btn btn-primary" data-modal-open="addPeerModal">
    <svg data-feather="plus"></svg> <?= __('addressbook.add_peer') ?>
  </button>
  <?php endif; ?>
</div>

<?php if (!$abGuid): ?>
<div class="card">
  <div class="empty-state">
    <svg data-feather="book"></svg>
    <h3><?= __('addressbook.no_book_title') ?></h3>
    <p><?= __('addressbook.no_book_desc') ?></p>
  </div>
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:1fr auto;gap:20px;align-items:start">

<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
    <svg data-feather="users" style="width:16px;height:16px;color:var(--accent)"></svg>
    <strong style="font-size:var(--font-sm)"><?= __p('addressbook.peers_count', count($peers)) ?></strong>
  </div>

  <?php if (empty($peers)): ?>
  <div class="empty-state" style="padding:40px">
    <svg data-feather="user-plus"></svg>
    <p><?= __('addressbook.no_peers') ?></p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th><?= __('addressbook.device_id') ?></th>
        <th><?= __('addressbook.alias') ?></th>
        <th><?= __('addressbook.host_user') ?></th>
        <th><?= __('addressbook.platform') ?></th>
        <th><?= __('addressbook.tags') ?></th>
        <th><?= __('addressbook.password') ?></th>
        <th><?= __('addressbook.note') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($peers as $p):
          $pTags = is_array($p['tags'] ?? null) ? $p['tags'] : [];
      ?>
      <tr>
        <td><code style="font-size:0.7rem"><?= htmlspecialchars($p['id'] ?? '') ?></code></td>
        <td><?= htmlspecialchars($p['alias'] ?? '') ?></td>
        <td>
          <?php if ($p['hostname'] ?? ''): ?>
            <div style="font-weight:600"><?= htmlspecialchars($p['hostname']) ?></div>
          <?php endif; ?>
          <?php if ($p['username'] ?? ''): ?>
            <div style="font-size:0.7rem;color:var(--text-muted)"><?= htmlspecialchars($p['username']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($p['platform'] ?? '') ?></td>
        <td>
          <?php foreach ($pTags as $t): ?>
            <span class="badge badge-info" style="margin:1px"><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </td>
        <td data-pw-cell="<?= htmlspecialchars($p['id'] ?? '') ?>" style="white-space:nowrap">
          <?php if (!empty($p['has_password'])): ?>
            <button type="button" class="btn-icon" title="<?= __('addressbook.password_reveal') ?>"
              onclick="abRevealPassword('<?= htmlspecialchars($abGuid) ?>','<?= htmlspecialchars($p['id'] ?? '') ?>',this)">
              <svg data-feather="eye" style="width:14px;height:14px"></svg>
            </button>
            <code class="ab-pw-value" style="display:none;font-size:0.7rem"></code>
          <?php else: ?>
            <span style="color:var(--text-muted);font-size:0.7rem"><?= __('addressbook.password_none') ?></span>
          <?php endif; ?>
          <button type="button" class="btn-icon" title="<?= __('addressbook.password_set') ?>"
            onclick="abEditPassword('<?= htmlspecialchars($abGuid) ?>','<?= htmlspecialchars($p['id'] ?? '') ?>',<?= !empty($p['has_password']) ? 'true' : 'false' ?>)">
            <svg data-feather="edit-2" style="width:14px;height:14px"></svg>
          </button>
        </td>
        <td style="color:var(--text-muted);font-size:0.75rem"><?= htmlspecialchars($p['note'] ?? '') ?></td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action"   value="delete_peer" />
            <input type="hidden" name="ab_guid"  value="<?= htmlspecialchars($abGuid) ?>" />
            <input type="hidden" name="peer_id"  value="<?= htmlspecialchars($p['id'] ?? '') ?>" />
            <?php if ($viewUserId !== $user['id']): ?>
            <input type="hidden" name="user_id"  value="<?= $viewUserId ?>" />
            <?php endif; ?>
            <button class="btn-icon danger" type="submit"
              data-confirm="<?= htmlspecialchars(__('addressbook.confirm_remove', $p['id'] ?? '')) ?>">
              <svg data-feather="trash-2"></svg>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<div style="min-width:220px">
  <div class="card" style="padding:0;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:8px">
        <svg data-feather="tag" style="width:16px;height:16px;color:var(--accent)"></svg>
        <strong style="font-size:var(--font-sm)"><?= __p('addressbook.tags_count', count($tags)) ?></strong>
      </div>
      <button class="btn-icon" title="<?= __('addressbook.add_tag') ?>" data-modal-open="addTagModal">
        <svg data-feather="plus"></svg>
      </button>
    </div>
    <?php if (empty($tags)): ?>
    <div style="padding:20px;color:var(--text-muted);font-size:var(--font-sm);text-align:center"><?= __('addressbook.no_tags') ?></div>
    <?php else: ?>
    <ul style="list-style:none;margin:0;padding:8px 0">
      <?php foreach ($tags as $tag): ?>
      <li style="display:flex;align-items:center;justify-content:space-between;padding:6px 16px;border-bottom:1px solid var(--border)">
        <span class="badge badge-info"><?= htmlspecialchars($tag['name'] ?? '') ?></span>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action"   value="delete_tag" />
          <input type="hidden" name="ab_guid"  value="<?= htmlspecialchars($abGuid) ?>" />
          <input type="hidden" name="tag_name" value="<?= htmlspecialchars($tag['name'] ?? '') ?>" />
          <button class="btn-icon danger" type="submit" title="<?= __('addressbook.remove_tag') ?>"
            data-confirm="<?= htmlspecialchars(__('addressbook.confirm_remove_tag', $tag['name'] ?? '')) ?>">
            <svg data-feather="x"></svg>
          </button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>

</div>
<?php endif; ?>

<?php if ($user['is_admin'] && !empty($userList)): ?>
<div class="card" style="margin-top:20px;padding:0;overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
    <svg data-feather="copy" style="width:16px;height:16px;color:var(--accent)"></svg>
    <strong style="font-size:var(--font-sm)"><?= __('addressbook.copy_title') ?></strong>
  </div>
  <form method="POST" style="padding:20px">
    <input type="hidden" name="action" value="copy_ab" />
    <p style="margin:0 0 16px;color:var(--text-muted);font-size:var(--font-sm)">
      <?= __('addressbook.copy_help') ?>
    </p>

    <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end">
      <div class="form-group" style="min-width:180px;margin:0">
        <label for="copyFrom"><?= __('addressbook.copy_from') ?></label>
        <select id="copyFrom" name="copy_from" required>
          <option value=""><?= __('addressbook.copy_pick') ?></option>
          <?php foreach ($userList as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['display_name'] ?: $u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="padding-bottom:10px;color:var(--text-muted)">
        <svg data-feather="arrow-right" style="width:18px;height:18px"></svg>
      </div>

      <div class="form-group" style="min-width:180px;margin:0">
        <label for="copyTo"><?= __('addressbook.copy_to') ?></label>
        <select id="copyTo" name="copy_to" required>
          <option value=""><?= __('addressbook.copy_pick') ?></option>
          <?php foreach ($userList as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['display_name'] ?: $u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="min-width:180px;margin:0">
        <label for="copyMode"><?= __('addressbook.copy_mode') ?></label>
        <select id="copyMode" name="copy_mode">
          <option value="merge"><?= __('addressbook.copy_mode_merge') ?></option>
          <option value="replace"><?= __('addressbook.copy_mode_replace') ?></option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary"
        data-confirm="<?= htmlspecialchars(__('addressbook.copy_confirm')) ?>">
        <svg data-feather="copy"></svg>
        <?= __('addressbook.copy_btn') ?>
      </button>
    </div>

    <div style="margin-top:14px">
      <label style="display:flex;align-items:center;gap:8px;font-size:var(--font-sm);cursor:pointer">
        <input type="checkbox" name="copy_passwords" value="1" id="copyPasswords" />
        <span><?= __('addressbook.copy_passwords_label') ?></span>
      </label>
      <div style="margin-top:6px;font-size:0.7rem;color:var(--text-muted)">
        <?= __('addressbook.copy_passwords_help') ?>
      </div>
    </div>

    <div style="margin-top:12px;font-size:0.7rem;color:var(--text-muted)">
      <svg data-feather="alert-triangle" style="width:12px;height:12px;vertical-align:-1px"></svg>
      <?= __('addressbook.copy_password_note') ?>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="modal-backdrop" id="addPeerModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title"><?= __('addressbook.add_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"  value="add_peer" />
      <input type="hidden" name="ab_guid" value="<?= htmlspecialchars($abGuid) ?>" />
      <?php if ($viewUserId !== $user['id']): ?>
      <input type="hidden" name="user_id" value="<?= $viewUserId ?>" />
      <?php endif; ?>
      <div class="modal-body">

        <?php if (!empty($allDevices)): ?>
        <div class="form-group" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)">
          <label for="devicePicker"><?= __('addressbook.pick_device') ?></label>
          <select id="devicePicker" onchange="abFillFromDevice(this)"
                  style="padding:8px 10px;border-radius:var(--radius);border:1px solid var(--border);background:var(--card);color:var(--text);width:100%;font-size:var(--font-sm)">
            <option value=""><?= __('addressbook.pick_device_placeholder') ?></option>
            <?php foreach ($allDevices as $d):
                $did   = $d['id'] ?? '';
                $dname = $d['info']['device_name'] ?? ($d['info']['hostname'] ?? $did);
                $dos   = $d['info']['os'] ?? '';
                $dusr  = $d['user_name'] ?? '';
                $label = $dname . ($dusr ? " ($dusr)" : '') . ($dos ? ' · ' . explode(' ', $dos)[0] : '');
            ?>
            <option value="<?= htmlspecialchars($did) ?>"
                    data-alias="<?= htmlspecialchars($dname) ?>"
                    data-note="<?= htmlspecialchars($dos) ?>">
              <?= htmlspecialchars($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px">
            <?= __('addressbook.pick_device_hint') ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom:16px">
          <label for="newPeerId"><?= __('addressbook.device_id_label') ?> <span style="color:var(--danger)">*</span></label>
          <input type="text" name="peer_id" id="newPeerId" required placeholder="<?= __('addressbook.device_id_placeholder') ?>" />
        </div>
        <div class="form-group" style="margin-bottom:16px">
          <label for="newAlias"><?= __('addressbook.alias_label') ?></label>
          <input type="text" name="alias" id="newAlias" placeholder="<?= __('addressbook.alias_placeholder') ?>" />
        </div>
        <div class="form-group">
          <label for="newNote"><?= __('addressbook.note_label') ?></label>
          <input type="text" name="note" id="newNote" placeholder="<?= __('addressbook.note_placeholder') ?>" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close><?= __('general.cancel') ?></button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="user-plus"></svg>
          <?= __('addressbook.add_peer') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function abFillFromDevice(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('newPeerId').value = opt.value;
    document.getElementById('newAlias').value  = opt.dataset.alias || '';
    document.getElementById('newNote').value   = opt.dataset.note  || '';
}
</script>

<div class="modal-backdrop" id="addTagModal">
  <div class="modal" style="max-width:380px">
    <div class="modal-header">
      <span class="modal-title"><?= __('addressbook.add_tag_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"  value="add_tag" />
      <input type="hidden" name="ab_guid" value="<?= htmlspecialchars($abGuid) ?>" />
      <?php if ($viewUserId !== $user['id']): ?>
      <input type="hidden" name="user_id" value="<?= $viewUserId ?>" />
      <?php endif; ?>
      <div class="modal-body">
        <div class="form-group">
          <label for="newTagName"><?= __('addressbook.tag_name_label') ?> <span style="color:var(--danger)">*</span></label>
          <input type="text" name="tag_name" id="newTagName" required placeholder="<?= __('addressbook.tag_name_placeholder') ?>" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close><?= __('general.cancel') ?></button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="tag"></svg>
          <?= __('addressbook.add_tag_btn') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="editPasswordModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title"><?= __('addressbook.password_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST" id="editPasswordForm">
      <input type="hidden" name="action"  value="set_password" />
      <input type="hidden" name="ab_guid" value="<?= htmlspecialchars($abGuid) ?>" />
      <input type="hidden" name="peer_id" id="pwPeerId" value="" />
      <?php if ($viewUserId !== $user['id']): ?>
      <input type="hidden" name="user_id" value="<?= $viewUserId ?>" />
      <?php endif; ?>
      <div class="modal-body">
        <div class="form-group">
          <label for="pwValue"><?= __('addressbook.password_label') ?></label>
          <input type="password" name="peer_password" id="pwValue" autocomplete="new-password"
                 placeholder="<?= __('addressbook.password_placeholder') ?>" />
          <div style="font-size:0.7rem;color:var(--text-muted);margin-top:6px">
            <?= __('addressbook.password_help') ?>
          </div>
        </div>
        <div id="pwClearRow" style="display:none;margin-top:12px">
          <label style="display:flex;align-items:center;gap:8px;font-size:var(--font-sm);cursor:pointer">
            <input type="checkbox" id="pwClear" onchange="abToggleClear(this)" />
            <span><?= __('addressbook.password_clear') ?></span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close><?= __('general.cancel') ?></button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="save"></svg>
          <?= __('addressbook.password_save_btn') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Entries per book, keyed by the AB guid, so reveal/edit know which book a row
// belongs to. Peer IDs can repeat across a book, hence the pair.
function abEditPassword(abGuid, peerId, hasExisting) {
    document.getElementById('pwPeerId').value = peerId;
    document.getElementById('pwValue').value = '';
    document.getElementById('pwClear').checked = false;
    document.getElementById('pwValue').disabled = false;
    // only offer "clear" when there is something to clear
    document.getElementById('pwClearRow').style.display = hasExisting ? 'block' : 'none';
    document.getElementById('editPasswordModal').classList.add('open');
}

function abToggleClear(cb) {
    var input = document.getElementById('pwValue');
    input.disabled = cb.checked;
    if (cb.checked) input.value = '';
}

function abRevealPassword(abGuid, peerId, btn) {
    var cell = btn.closest('td');
    var out  = cell.querySelector('.ab-pw-value');
    if (!out) return;

    // toggle back off if already showing
    if (out.style.display !== 'none') {
        out.style.display = 'none';
        out.textContent = '';
        return;
    }

    btn.disabled = true;
    fetch('ajax/reveal-password.php?ab=' + encodeURIComponent(abGuid) +
          '&peer=' + encodeURIComponent(peerId), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            if (d && d.password) {
                out.textContent = d.password;
                out.style.display = 'inline';
            } else {
                out.textContent = d && d.error ? d.error : 'unavailable';
                out.style.display = 'inline';
            }
        })
        .catch(function () {
            btn.disabled = false;
            out.textContent = 'unavailable';
            out.style.display = 'inline';
        });
}
</script>

<?php page_close(); ?>
