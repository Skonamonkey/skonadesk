<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$info       = api_get('/server-info');
$pubKey     = $info['public_key'] ?? '';
$domain     = $info['domain']     ?? '';
$isIp       = (bool) preg_match('/^\d+\.\d+\.\d+\.\d+$/', $domain);
$apiScheme  = $isIp ? 'http' : 'https';
$apiPort    = $isIp ? ':21114' : '';
$apiUrl     = API_PUBLIC_URL ?: ($domain ? $apiScheme . '://' . $domain . $apiPort : ''); // API_PUBLIC_URL takes priority — bug fix, thanks devastgh (github.com/devastgh)

$httpHost   = $_SERVER['HTTP_HOST'] ?? '';
$dashPort   = (int)(parse_url('http://' . $httpHost, PHP_URL_PORT) ?: ($isIp ? 8080 : 443));

page_open(__('server.title'));
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px" class="server-grid">

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="key"></svg>
        <?= __('server.public_key') ?>
      </div>
    </div>
    <div class="card-body">
      <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:16px">
        <?= __('server.copy_key_desc') ?>
      </p>
      <?php if ($pubKey): ?>
      <div class="copy-wrap">
        <code class="code-block" id="pubKey"><?= htmlspecialchars($pubKey) ?></code>
        <button class="copy-btn" data-copy="#pubKey" title="<?= __('general.copy') ?>">
          <svg data-feather="copy"></svg>
        </button>
      </div>
      <?php else: ?>
      <div class="alert alert-warning">
        <?= __('server.key_not_found', '/data/id_ed25519.pub') ?><br>
        <?= __('server.key_not_found_hint') ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="server"></svg>
        <?= __('server.server_addresses') ?>
      </div>
    </div>
    <div class="card-body">
      <?php if ($domain): ?>
      <div class="info-row">
        <span class="info-label"><?= __('server.rendezvous_label') ?></span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="hbbsHost"><?= htmlspecialchars($domain) ?></code>
          <button class="copy-btn" data-copy="#hbbsHost" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label"><?= __('server.relay_label') ?></span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="hbbrHost"><?= htmlspecialchars($domain) ?></code>
          <button class="copy-btn" data-copy="#hbbrHost" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label"><?= __('server.step3_api') ?></span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="apiUrl"><?= htmlspecialchars($apiUrl) ?></code>
          <button class="copy-btn" data-copy="#apiUrl" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <?php else: ?>
      <div class="alert alert-warning"><?= __('server.domain_not_configured') ?></div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="book-open"></svg>
      <?= __('server.setup_guide') ?>
    </div>
  </div>
  <div class="card-body">
    <p style="font-size:var(--font-sm);color:var(--text-secondary);margin-bottom:20px">
      <?= __('server.setup_desc') ?>
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px">

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">1</span>
          <strong><?= __('server.step2_title') ?></strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          <?= __('server.step2_desc') ?>
        </p>
      </div>

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">2</span>
          <strong><?= __('server.step3_title') ?></strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          Set <strong><?= __('server.step3_id_server') ?> / <?= __('server.step3_relay') ?></strong> to <code style="color:var(--color-primary)"><?= htmlspecialchars($domain ?: 'your-server') ?></code><br/>
          Set <strong><?= __('server.step3_api') ?></strong> to <code style="color:var(--color-primary)"><?= htmlspecialchars($apiUrl ?: $apiScheme . '://your-server' . $apiPort) ?></code>
        </p>
      </div>

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">3</span>
          <strong><?= __('server.step3_key') ?></strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          <?= __('server.step_key_desc') ?>
        </p>
      </div>

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">4</span>
          <strong><?= __('server.step4_title') ?></strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          <?= __('server.step4_desc') ?>
        </p>
      </div>

    </div>

    <div class="alert alert-info" style="margin-top:20px">
      <?= __('server.step4_warning') ?>
    </div>
  </div>
</div>

<?php if ($domain && $pubKey): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="download"></svg>
      <?= __('server.quick_config') ?>
    </div>
  </div>
  <div class="card-body">
    <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:20px">
      <?= __('server.quick_config_desc') ?>
    </p>

    <div style="margin-bottom:20px">
      <button class="btn btn-primary" onclick="downloadToml()" style="display:inline-flex;align-items:center;gap:6px">
        <svg data-feather="download" style="width:14px;height:14px"></svg>
        <?= __('server.download_toml') ?>
      </button>
      <span style="font-size:var(--font-sm);color:var(--text-muted);margin-left:12px">
        <?= __('server.download_toml_desc') ?>
      </span>
    </div>

    <div style="display:flex;gap:0;border-bottom:1px solid var(--border-color);margin-bottom:16px">
      <?php foreach (['Windows','Linux','macOS'] as $i => $tab): ?>
      <button class="qc-tab<?= $i === 0 ? ' qc-tab-active' : '' ?>"
              onclick="showTab('<?= strtolower($tab) ?>', this)"
              style="padding:6px 16px;font-size:var(--font-sm);background:none;border:none;border-bottom:2px solid <?= $i===0 ? 'var(--color-primary)' : 'transparent' ?>;color:<?= $i===0 ? 'var(--color-primary)' : 'var(--text-muted)' ?>;cursor:pointer;font-weight:<?= $i===0 ? '600' : '400' ?>">
        <?= __('server.tab_' . strtolower($tab)) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <div id="tab-windows">
      <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:8px">
        <?= __('server.powershell_heading') ?>
      </p>
      <div class="copy-wrap" style="align-items:flex-start">
        <code class="code-block" id="ps1cmd" style="white-space:pre-wrap;font-size:0.72rem;line-height:1.6"><?= htmlspecialchars(
          '$cfg = "$env:APPDATA\RustDesk\config"; mkdir -Force $cfg | Out-Null' . "\n" .
          '@"' . "\n" .
          'rendezvous_server = \'' . $domain . ':21116\'' . "\n\n" .
          '[options]' . "\n" .
          'custom-rendezvous-server = \'' . $domain . '\'' . "\n" .
          'relay-server = \'' . $domain . '\'' . "\n" .
          'api-server = \'' . $apiUrl . '\'' . "\n" .
          'key = \'' . $pubKey . '\'' . "\n" .
          '"@ | Out-File -FilePath "$cfg\RustDesk2.toml" -Encoding UTF8' . "\n" .
          'Write-Host "Config written. Restart RustDesk."'
        ) ?></code>
        <button class="copy-btn" data-copy="#ps1cmd" title="Copy" style="flex-shrink:0;margin-top:2px">
          <svg data-feather="copy"></svg>
        </button>
      </div>
      <p style="font-size:0.72rem;color:var(--text-muted);margin-top:8px">
        <?= __('server.config_location_windows') ?>
      </p>
    </div>

    <div id="tab-linux" style="display:none">
      <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:8px">
        <?= __('server.terminal_heading') ?>
      </p>
      <div class="copy-wrap" style="align-items:flex-start">
        <code class="code-block" id="bashcmd" style="white-space:pre-wrap;font-size:0.72rem;line-height:1.6"><?= htmlspecialchars(
          'mkdir -p ~/.config/rustdesk && cat > ~/.config/rustdesk/RustDesk2.toml << \'RDEOF\'' . "\n" .
          'rendezvous_server = \'' . $domain . ':21116\'' . "\n\n" .
          '[options]' . "\n" .
          'custom-rendezvous-server = \'' . $domain . '\'' . "\n" .
          'relay-server = \'' . $domain . '\'' . "\n" .
          'api-server = \'' . $apiUrl . '\'' . "\n" .
          'key = \'' . $pubKey . '\'' . "\n" .
          'RDEOF'
        ) ?></code>
        <button class="copy-btn" data-copy="#bashcmd" title="Copy" style="flex-shrink:0;margin-top:2px">
          <svg data-feather="copy"></svg>
        </button>
      </div>
      <p style="font-size:0.72rem;color:var(--text-muted);margin-top:8px">
        <?= __('server.config_location_linux') ?>
      </p>
    </div>

    <div id="tab-macos" style="display:none">
      <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:8px">
        <?= __('server.terminal_heading') ?>
      </p>
      <div class="copy-wrap" style="align-items:flex-start">
        <code class="code-block" id="maccmd" style="white-space:pre-wrap;font-size:0.72rem;line-height:1.6"><?= htmlspecialchars(
          'mkdir -p ~/Library/Application\ Support/RustDesk/config && cat > ~/Library/Application\ Support/RustDesk/config/RustDesk2.toml << \'RDEOF\'' . "\n" .
          'rendezvous_server = \'' . $domain . ':21116\'' . "\n\n" .
          '[options]' . "\n" .
          'custom-rendezvous-server = \'' . $domain . '\'' . "\n" .
          'relay-server = \'' . $domain . '\'' . "\n" .
          'api-server = \'' . $apiUrl . '\'' . "\n" .
          'key = \'' . $pubKey . '\'' . "\n" .
          'RDEOF'
        ) ?></code>
        <button class="copy-btn" data-copy="#maccmd" title="Copy" style="flex-shrink:0;margin-top:2px">
          <svg data-feather="copy"></svg>
        </button>
      </div>
      <p style="font-size:0.72rem;color:var(--text-muted);margin-top:8px">
        <?= __('server.config_location_macos') ?>
      </p>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($domain && $pubKey):
    $cfgPayload = json_encode([
        'host'  => $domain,
        'relay' => '',
        'api'   => $apiUrl,
        'key'   => $pubKey,
    ], JSON_UNESCAPED_SLASHES);
    $cfgString = strrev(rtrim(strtr(base64_encode($cfgPayload), '+/', '-_'), '='));
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="package"></svg>
      <?= __('server.preconfigured_title') ?>
    </div>
  </div>
  <div class="card-body">
    <p style="font-size:var(--font-sm);color:var(--text-secondary);margin-bottom:16px">
      <?= __('server.preconfigured_desc') ?>
    </p>
    <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:12px">
      <?= __('server.preconfigured_rename') ?>
    </p>
    <div style="display:grid;gap:8px;margin-bottom:16px">
      <div>
        <span style="font-size:0.7rem;color:var(--text-muted);display:block;margin-bottom:4px"><?= __('server.platform_windows') ?></span>
        <div class="copy-wrap">
          <code class="code-block" id="cfgFilenameWin">rustdesk-<?= htmlspecialchars($cfgString) ?>.exe</code>
          <button class="copy-btn" data-copy="#cfgFilenameWin" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <div>
        <span style="font-size:0.7rem;color:var(--text-muted);display:block;margin-bottom:4px"><?= __('server.platform_linux') ?></span>
        <div class="copy-wrap">
          <code class="code-block" id="cfgFilenameLinux">rustdesk-<?= htmlspecialchars($cfgString) ?>.AppImage</code>
          <button class="copy-btn" data-copy="#cfgFilenameLinux" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <div>
        <span style="font-size:0.7rem;color:var(--text-muted);display:block;margin-bottom:4px"><?= __('server.platform_macos') ?></span>
      </div>
    </div>
    <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:4px">
      <?= __('server.universal_import') ?>
    </p>
    <div class="copy-wrap">
      <code class="code-block" id="cfgString"><?= htmlspecialchars($cfgString) ?></code>
      <button class="copy-btn" data-copy="#cfgString" title="<?= __('general.copy') ?>"><svg data-feather="copy"></svg></button>
    </div>
    <!-- config string algorithm credit: devastgh (github.com/devastgh) -->
    <p style="font-size:0.72rem;color:var(--text-muted);margin-top:12px">
      <strong><?= __('server.relay_note_title') ?></strong> <?= __('server.relay_note') ?>
    </p>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="shield"></svg>
      <?= __('server.firewall_title') ?>
    </div>
  </div>
  <div class="card-body">
    <table style="width:100%;border-collapse:collapse;font-size:var(--font-sm)">
      <thead>
        <tr style="border-bottom:1px solid var(--border-color)">
          <th style="text-align:left;padding:6px 12px;color:var(--text-muted);font-weight:500"><?= __('server.firewall_port') ?></th>
          <th style="text-align:left;padding:6px 12px;color:var(--text-muted);font-weight:500"><?= __('server.firewall_protocol') ?></th>
          <th style="text-align:left;padding:6px 12px;color:var(--text-muted);font-weight:500"><?= __('server.firewall_purpose') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $ports = [
            ['21115', 'TCP',     __('server.nat_test')],
            ['21116', 'TCP/UDP', __('server.rendezvous_desc')],
            ['21117', 'TCP',     __('server.relay_desc')],
            ['21118', 'TCP',     __('server.ws_rendezvous')],
            ['21119', 'TCP',     __('server.ws_relay')],
        ];
        if ($isIp) {
            array_unshift($ports, ['21114', 'TCP', __('server.firewall_port_direct')]);
            $ports[] = [(string)$dashPort, 'TCP', __('server.firewall_port_dash')];
        } else {
            $ports[] = ['443', 'TCP', __('server.api_dash')];
        }
        foreach ($ports as [$port, $proto, $desc]): ?>
        <tr style="border-bottom:1px solid var(--border-color)">
          <td style="padding:6px 12px"><code><?= $port ?></code></td>
          <td style="padding:6px 12px;color:var(--text-muted)"><?= $proto ?></td>
          <td style="padding:6px 12px;color:var(--text-muted)"><?= $desc ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:var(--font-sm);color:var(--text-muted);margin:12px 0 0">
      <?= __('server.firewall_note') ?>
    </p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="shield"></svg>
      <?= __('server.security_title') ?>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;gap:12px;font-size:var(--font-sm)">
      <div class="info-row">
        <span class="info-label"><?= __('server.security_key_check') ?></span>
        <div class="info-value">
          <span class="badge badge-info"><?= __('server.security_key_check') ?></span>
          <span style="color:var(--text-muted);margin-left:8px">
            <?= __('server.security_key_check_desc') ?>
          </span>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label"><?= __('server.security_transport') ?></span>
        <div class="info-value">
          <?php if ($isIp): ?>
          <span class="badge badge-warning">HTTP</span>
          <span style="color:var(--text-muted);margin-left:8px"><?= __('server.security_transport_http') ?></span>
          <?php else: ?>
          <span class="badge badge-active">HTTPS</span>
          <span style="color:var(--text-muted);margin-left:8px"><?= __('server.security_transport_https') ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label"><?= __('server.security_e2e') ?></span>
        <div class="info-value">
          <span class="badge badge-active">Active</span>
          <span style="color:var(--text-muted);margin-left:8px">
            <?= __('server.security_e2e_desc') ?>
          </span>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label"><?= __('server.security_relay_auth') ?></span>
        <div class="info-value">
          <span class="badge badge-active">Active</span>
          <span style="color:var(--text-muted);margin-left:8px">
            <?= __('server.security_relay_auth_desc') ?>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="grid"></svg>
      <?= __('server.matrix_title') ?>
    </div>
  </div>
  <div class="card-body" style="font-size:var(--font-sm)">
    <p style="color:var(--text-muted);margin:0 0 12px">
      <?= __('server.matrix_desc') ?>
    </p>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:var(--font-sm)">
        <thead>
          <tr style="border-bottom:1px solid var(--border)">
            <th style="text-align:center;padding:6px 8px;color:var(--text-muted);font-weight:600" colspan="3"><?= __('server.matrix_header_caller') ?></th>
            <th style="text-align:center;padding:6px 8px;color:var(--text-muted);font-weight:600" colspan="3"><?= __('server.matrix_header_callee') ?></th>
            <th style="text-align:left;padding:6px 8px;color:var(--text-muted);font-weight:600"><?= __('server.matrix_header_result') ?></th>
          </tr>
          <tr style="border-bottom:1px solid var(--border)">
            <th style="text-align:center;padding:4px 8px;color:var(--text-muted);font-weight:500"><?= __('server.matrix_header_server') ?></th>
            <th style="text-align:center;padding:4px 8px;color:var(--text-muted);font-weight:500"><?= __('server.matrix_header_login') ?></th>
            <th style="text-align:center;padding:4px 8px;color:var(--text-muted);font-weight:500"><?= __('server.matrix_header_key') ?></th>
            <th style="text-align:center;padding:4px 8px;color:var(--text-muted);font-weight:500"><?= __('server.matrix_header_server') ?></th>
            <th style="text-align:center;padding:4px 8px;color:var(--text-muted);font-weight:500"><?= __('server.matrix_header_login') ?></th>
            <th style="text-align:center;padding:4px 8px;color:var(--text-muted);font-weight:500"><?= __('server.matrix_header_key') ?></th>
            <th style="padding:4px 8px"></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = [
            ['✅','✅','✅','✅','—','✅',__('server.matrix_row_works_both_encrypted')],
            ['✅','✅','✅','✅','—','❌',__('server.matrix_row_caller_encrypted_callee_plain')],
            ['✅','✅','❌','✅','—','✅',__('server.matrix_row_callee_encrypted_caller_plain')],
            ['✅','✅','❌','✅','—','❌',__('server.matrix_row_both_plain')],
            ['✅','❌','✅','✅','—','✅',__('server.matrix_row_caller_not_logged')],
            ['✅','❌','❌','✅','—','✅',__('server.matrix_row_caller_not_logged')],
            ['✅','✅','✅','❌','—','—',__('server.matrix_row_callee_not_registered')],
            ['❌','—','—','✅','—','✅',__('server.matrix_row_caller_no_server')],
          ];
          foreach ($rows as $i => $r):
            $bg = $i % 2 === 0 ? 'background:var(--surface-alt,rgba(0,0,0,.03))' : '';
            $ok = $r[0] === '✅' && $r[1] === '✅' && $r[3] === '✅';
          ?>
          <tr style="<?= $bg ?>">
            <?php for ($c = 0; $c < 6; $c++): ?>
            <td style="text-align:center;padding:6px 8px"><?= $r[$c] ?></td>
            <?php endfor; ?>
            <td style="padding:6px 8px;color:<?= $ok ? 'var(--color-active,#22c55e)' : 'var(--color-danger,#ef4444)' ?>"><?= htmlspecialchars($r[6]) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="color:var(--text-muted);margin:12px 0 0">
      <?= __('server.matrix_legend_not_applicable') ?> &nbsp;|&nbsp;
      <?= __('server.matrix_legend_login') ?> &nbsp;|&nbsp;
      <?= __('server.matrix_legend_key') ?>
    </p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="alert-triangle"></svg>
      <?= __('server.troubleshooting_title') ?>
    </div>
  </div>
  <div class="card-body" style="display:grid;gap:16px;font-size:var(--font-sm)">
    <div>
      <strong><?= __('server.trouble_key_mismatch') ?></strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        <?= __('server.trouble_key_mismatch_desc') ?>
      </p>
    </div>
    <div>
      <strong><?= __('server.trouble_secure_tcp') ?></strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        <?= __('server.trouble_secure_tcp_desc') ?>
      </p>
    </div>
    <div>
      <strong><?= __('server.trouble_sync') ?></strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        <?php if (!$isIp): ?>
          <?= __('server.trouble_sync_desc_ssl', $apiUrl) ?>
        <?php else: ?>
          <?= __('server.trouble_sync_desc_http') ?>
        <?php endif; ?>
      </p>
    </div>
    <div>
      <strong><?= __('server.trouble_lan') ?></strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        <?= __('server.trouble_lan_desc') ?>
      </p>
    </div>
    <div>
      <strong><?= __('server.trouble_login_title') ?></strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        <?= __('server.trouble_login_desc') ?>
      </p>
    </div>
  </div>
</div>

<script>
<?php if ($domain && $pubKey): ?>
(function () {
    const tomlContent = <?= json_encode(
        "rendezvous_server = '" . $domain . ":21116'\n\n" .
        "[options]\n" .
        "custom-rendezvous-server = '" . $domain . "'\n" .
        "relay-server = '" . $domain . "'\n" .
        "api-server = '" . $apiUrl . "'\n" .
        "key = '" . $pubKey . "'\n"
    ) ?>;

    window.downloadToml = function () {
        const blob = new Blob([tomlContent], { type: 'text/plain' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'RustDesk2.toml';
        a.click();
        URL.revokeObjectURL(a.href);
    };

    window.showTab = function (name, btn) {
        ['windows','linux','macos'].forEach(function (t) {
            const el = document.getElementById('tab-' + t);
            if (el) el.style.display = (t === name) ? '' : 'none';
        });
        document.querySelectorAll('.qc-tab').forEach(function (b) {
            b.style.borderBottomColor = 'transparent';
            b.style.color = 'var(--text-muted)';
            b.style.fontWeight = '400';
        });
        btn.style.borderBottomColor = 'var(--color-primary)';
        btn.style.color = 'var(--color-primary)';
        btn.style.fontWeight = '600';
    };
}());
<?php endif; ?>
</script>

<?php page_close(); ?>
