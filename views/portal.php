<div class="portal-header">
    <h1>Network Access</h1>
    <div class="portal-actions">
        <button class="help-toggle" title="Help">?</button>
        <button id="btn-grant-all" class="btn btn-primary btn-sm">Activate All</button>
        <button id="btn-revoke-all" class="btn btn-danger btn-sm">Deactivate All</button>
    </div>
</div>
<div class="help-panel">
    <h3>Network Access Portal</h3>
    <p>This page shows the network resources assigned to your account.</p>
    <ul>
        <li><strong>Activate</strong> — Opens access to the resource. Your WireGuard IP is added to the MikroTik firewall</li>
        <li><strong>Deactivate</strong> — Closes access immediately</li>
        <li><strong>Timer</strong> — Shows remaining time. Access auto-expires when timer reaches zero</li>
        <li><strong>Keepalive</strong> — While this page is open, access is automatically refreshed every 5 minutes. If you close the browser, access expires after the timeout</li>
        <li><strong>Activate All / Deactivate All</strong> — Batch activate or deactivate all your resources</li>
    </ul>
    <p>If you see a connection error, the MikroTik API may be unreachable — contact your administrator.</p>
</div>

<div id="portal-error" class="alert alert-danger" style="display:none"></div>
<div id="portal-warning" class="alert alert-warning" style="display:none"></div>

<div id="resources-grid" class="resources-grid">
    <?php if (empty($resources)): ?>
    <div class="empty-state">
        <p>No resources assigned to your account.</p>
        <p class="text-muted">Contact an administrator to get access.</p>
    </div>
    <?php endif; ?>

    <?php foreach ($resources ?? [] as $resource):
        // Prefer domain_name if set, fall back to dst_address. First port only (in case of 445,139 etc).
        $autoOpenHost = !empty($resource['domain_name']) ? $resource['domain_name'] : $resource['dst_address'];
        $firstPort = preg_split('/[,\s]+/', (string) $resource['dst_port'])[0] ?? '';
        $autoOpenUrl = $firstPort !== '' ? sprintf('https://%s:%s', $autoOpenHost, $firstPort) : '';
    ?>
    <div class="resource-card <?= $resource['active'] ? 'resource-active' : 'resource-inactive' ?>"
         data-resource-id="<?= $resource['id'] ?>"
         data-expires="<?= $resource['expires_in_seconds'] ?>"
         data-auto-open-url="<?= htmlspecialchars($autoOpenUrl, ENT_QUOTES) ?>">
        <div class="resource-status">
            <span class="status-dot <?= $resource['active'] ? 'dot-active' : 'dot-inactive' ?>"></span>
            <span class="status-label"><?= $resource['active'] ? 'ACTIVE' : 'INACTIVE' ?></span>
        </div>
        <h3 class="resource-name"><?= htmlspecialchars($resource['name']) ?></h3>
        <?php if ($resource['description']): ?>
        <p class="resource-desc"><?= htmlspecialchars($resource['description']) ?></p>
        <?php endif; ?>
        <div class="resource-meta">
            <span class="meta-item"><?= htmlspecialchars($resource['dst_address']) ?></span>
            <span class="meta-item"><?= htmlspecialchars($resource['dst_port']) ?>/<?= htmlspecialchars($resource['protocol']) ?></span>
            <span class="meta-item"><?= $resource['timeout_minutes'] ?>min</span>
        </div>
        <?php if ($resource['active']): ?>
        <div class="resource-timer">
            <span class="timer-label">Expires in</span>
            <span class="timer-value" data-seconds="<?= $resource['expires_in_seconds'] ?>">
                <?= sprintf('%02d:%02d', intdiv($resource['expires_in_seconds'], 60), $resource['expires_in_seconds'] % 60) ?>
            </span>
        </div>
        <button class="btn btn-danger btn-sm btn-revoke" data-resource-id="<?= $resource['id'] ?>">Deactivate</button>
        <?php else: ?>
        <button class="btn btn-primary btn-sm btn-grant" data-resource-id="<?= $resource['id'] ?>">Activate</button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
