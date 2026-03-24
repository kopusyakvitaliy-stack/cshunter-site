<?php
/**
 * Admin Layout
 * Виклик: adminLayoutStart($title, $activePage) ... adminLayoutEnd()
 */

function adminLayoutStart(string $title, string $activePage): void {
    global $admin;
    $csrf = getCsrfToken();
    ?>
<!DOCTYPE html>
<html lang="uk" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — CSHunter Admin</title>
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= $csrf ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ════════════════════════════════════════════════════════════
   CSHunter Admin Panel — Design System
   Тема: Refined Dark / Laravel Nova Inspired
════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:         #0f1117;
    --surface:    #161b27;
    --surface-2:  #1c2233;
    --surface-3:  #212840;
    --border:     rgba(255,255,255,.07);
    --border-2:   rgba(255,255,255,.12);

    --text-1:  #e8ecf4;
    --text-2:  #9aa3b8;
    --text-3:  #5c677d;

    --accent:    #4f8ef7;
    --accent-h:  #6ba3ff;
    --green:     #34d399;
    --red:       #f87171;
    --yellow:    #fbbf24;
    --purple:    #a78bfa;

    --sidebar-w: 240px;
    --header-h:  56px;
    --radius:    8px;
    --radius-lg: 12px;

    --shadow:    0 1px 3px rgba(0,0,0,.4), 0 1px 2px rgba(0,0,0,.3);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.6);

    --font:      'Geist', -apple-system, sans-serif;
    --mono:      'Geist Mono', 'Courier New', monospace;
    --transition: .15s ease;
}

html, body { height: 100%; }
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text-1);
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

a { color: var(--accent); text-decoration: none; }
a:hover { color: var(--accent-h); }

/* ── Layout ── */
.adm-layout { display: flex; min-height: 100vh; }

/* ── Sidebar ── */
.adm-sidebar {
    width: var(--sidebar-w);
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    overflow-y: auto;
}

.adm-sidebar-logo {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0;
}
.adm-sidebar-logo-icon {
    width: 32px; height: 32px;
    background: var(--accent);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.adm-sidebar-logo-text {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: .3px;
}
.adm-sidebar-logo-sub {
    font-size: 10px;
    color: var(--text-3);
    font-family: var(--mono);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.adm-nav { padding: 12px 0; flex: 1; }

.adm-nav-section { margin-bottom: 4px; }

.adm-nav-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--text-3);
    padding: 12px 20px 4px;
}

.adm-nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 20px;
    color: var(--text-2);
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 0;
    transition: background var(--transition), color var(--transition);
    position: relative;
    cursor: pointer;
}
.adm-nav-item svg {
    width: 16px; height: 16px;
    stroke: currentColor;
    flex-shrink: 0;
    opacity: .7;
    transition: opacity var(--transition);
}
.adm-nav-item:hover {
    background: rgba(255,255,255,.04);
    color: var(--text-1);
}
.adm-nav-item:hover svg { opacity: 1; }
.adm-nav-item.active {
    background: rgba(79,142,247,.1);
    color: var(--accent);
}
.adm-nav-item.active svg { opacity: 1; }
.adm-nav-item.active::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 2px;
    background: var(--accent);
    border-radius: 0 2px 2px 0;
}

.adm-nav-badge {
    margin-left: auto;
    background: var(--accent);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    line-height: 1.3;
}

.adm-sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
}
.adm-sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
}
.adm-sidebar-user img {
    width: 32px; height: 32px;
    border-radius: 6px;
    object-fit: cover;
    flex-shrink: 0;
}
.adm-sidebar-user-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 130px;
}
.adm-sidebar-user-role {
    font-size: 10px;
    color: var(--accent);
    font-family: var(--mono);
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* ── Main content ── */
.adm-main {
    margin-left: var(--sidebar-w);
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.adm-header {
    height: var(--header-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    padding: 0 28px;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 50;
}
.adm-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-3);
    font-size: 13px;
    font-family: var(--mono);
}
.adm-breadcrumb span { color: var(--text-2); }
.adm-header-spacer { flex: 1; }
.adm-header-site-link {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-2);
    padding: 5px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: all var(--transition);
}
.adm-header-site-link:hover {
    border-color: var(--border-2);
    color: var(--text-1);
}

.adm-content {
    padding: 28px;
    flex: 1;
    max-width: 1300px;
    width: 100%;
}

/* ── Page header ── */
.adm-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
}
.adm-page-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: -.3px;
}
.adm-page-sub {
    font-size: 13px;
    color: var(--text-3);
    margin-top: 2px;
}
.adm-page-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ── Cards ── */
.adm-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    overflow: hidden;
}
.adm-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    gap: 12px;
}
.adm-card-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-1);
}
.adm-link {
    font-size: 12px;
    color: var(--text-3);
    transition: color var(--transition);
}
.adm-link:hover { color: var(--accent); }

/* ── Stats grid ── */
.adm-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
@media (max-width: 1100px) { .adm-stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .adm-stats-grid { grid-template-columns: 1fr; } }

.adm-stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: border-color var(--transition);
}
.adm-stat-card:hover { border-color: var(--border-2); }

.adm-stat-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.adm-stat-icon svg { width: 18px; height: 18px; }
.adm-stat-icon--blue   { background: rgba(79,142,247,.15);  color: #4f8ef7; }
.adm-stat-icon--green  { background: rgba(52,211,153,.15);  color: #34d399; }
.adm-stat-icon--yellow { background: rgba(251,191,36,.15);  color: #fbbf24; }
.adm-stat-icon--red    { background: rgba(248,113,113,.15); color: #f87171; }

.adm-stat-body { flex: 1; min-width: 0; }
.adm-stat-num {
    font-size: 26px;
    font-weight: 700;
    color: var(--text-1);
    line-height: 1;
    font-family: var(--mono);
}
.adm-stat-lbl {
    font-size: 12px;
    color: var(--text-3);
    margin-top: 3px;
}
.adm-stat-trend {
    font-size: 11px;
    color: var(--text-3);
    margin-top: auto;
    padding-top: 8px;
    align-self: flex-end;
}
.adm-stat-trend--up { color: var(--green); }

/* ── Grid ── */
.adm-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
@media (max-width: 900px) { .adm-grid-2 { grid-template-columns: 1fr; } }

/* ── Table ── */
.adm-table-wrap { overflow-x: auto; }
.adm-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.adm-table th {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-3);
    padding: 10px 20px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.adm-table td {
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.adm-table tbody tr:last-child td { border-bottom: none; }
.adm-table-hover tbody tr:hover td { background: rgba(255,255,255,.02); }
.adm-row-banned td { opacity: .55; }
.adm-empty {
    text-align: center;
    color: var(--text-3);
    padding: 40px !important;
    font-size: 13px;
}

/* ── User cell ── */
.adm-user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.adm-avatar {
    width: 28px; height: 28px;
    border-radius: 5px;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--surface-3);
}
.adm-avatar-lg { width: 36px; height: 36px; border-radius: 7px; }
.adm-user-name { font-size: 13.5px; font-weight: 500; color: var(--text-1); }
.adm-user-sub  { font-size: 11px; color: var(--text-3); font-family: var(--mono); margin-top: 1px; }

/* ── Badges ── */
.adm-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .3px;
    white-space: nowrap;
    font-family: var(--mono);
}
.adm-badge-green  { background: rgba(52,211,153,.15);  color: #34d399; }
.adm-badge-red    { background: rgba(248,113,113,.15); color: #f87171; }
.adm-badge-blue   { background: rgba(79,142,247,.15);  color: #4f8ef7; }
.adm-badge-yellow { background: rgba(251,191,36,.15);  color: #fbbf24; }
.adm-badge-gray   { background: rgba(255,255,255,.06); color: var(--text-2); }

/* ── Buttons ── */
.adm-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: var(--radius);
    font-size: 13px;
    font-weight: 500;
    font-family: var(--font);
    cursor: pointer;
    border: 1px solid transparent;
    transition: all var(--transition);
    white-space: nowrap;
    line-height: 1;
}
.adm-btn:disabled { opacity: .4; cursor: not-allowed; }

.adm-btn-primary  { background: var(--accent); color: #fff; border-color: var(--accent); }
.adm-btn-primary:hover { background: var(--accent-h); border-color: var(--accent-h); color: #fff; }

.adm-btn-secondary {
    background: var(--surface-3);
    color: var(--text-1);
    border-color: var(--border-2);
}
.adm-btn-secondary:hover { background: var(--surface-2); border-color: rgba(255,255,255,.2); color: var(--text-1); }

.adm-btn-danger   { background: rgba(248,113,113,.15); color: var(--red); border-color: rgba(248,113,113,.3); }
.adm-btn-danger:hover { background: rgba(248,113,113,.25); color: var(--red); }

.adm-btn-danger-ghost { background: transparent; color: var(--text-3); border-color: transparent; }
.adm-btn-danger-ghost:hover { color: var(--red); background: rgba(248,113,113,.1); }

.adm-btn-ghost { background: transparent; color: var(--text-2); border-color: var(--border); }
.adm-btn-ghost:hover { background: var(--surface-3); color: var(--text-1); }

.adm-btn-sm  { padding: 5px 10px; font-size: 12px; }
.adm-btn-xs  { padding: 3px 8px; font-size: 11px; }
.adm-btn-lg  { padding: 10px 22px; font-size: 14px; }

/* ── Inputs ── */
.adm-input {
    background: var(--bg);
    border: 1px solid var(--border-2);
    color: var(--text-1);
    border-radius: var(--radius);
    padding: 7px 12px;
    font-size: 13px;
    font-family: var(--font);
    width: 100%;
    transition: border-color var(--transition);
    outline: none;
}
.adm-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(79,142,247,.15); }
.adm-input::placeholder { color: var(--text-3); }

.adm-input-search { min-width: 260px; }
.adm-input-ip     { max-width: 160px; font-family: var(--mono); font-size: 12px; }
.adm-input-port   { max-width: 90px;  font-family: var(--mono); font-size: 12px; }
.adm-input-tags   { max-width: 200px; }

.adm-select {
    background: var(--bg);
    border: 1px solid var(--border-2);
    color: var(--text-1);
    border-radius: var(--radius);
    padding: 7px 10px;
    font-size: 13px;
    font-family: var(--font);
    cursor: pointer;
    outline: none;
    transition: border-color var(--transition);
}
.adm-select:focus { border-color: var(--accent); }

.adm-label {
    font-size: 12px;
    color: var(--text-3);
    margin-bottom: 4px;
    display: block;
}

/* ── Filters ── */
.adm-filters { margin-bottom: 16px; }
.adm-filter-form {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* ── Alerts ── */
.adm-alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    font-size: 13.5px;
    margin-bottom: 16px;
    border: 1px solid;
}
.adm-alert-success { background: rgba(52,211,153,.08); border-color: rgba(52,211,153,.25); color: var(--green); }
.adm-alert-error   { background: rgba(248,113,113,.08); border-color: rgba(248,113,113,.25); color: var(--red); }
.adm-alert-warn    { background: rgba(251,191,36,.08);  border-color: rgba(251,191,36,.25);  color: var(--yellow); }
.adm-alert-warn code { background: rgba(251,191,36,.12); border-radius: 4px; padding: 1px 5px; font-family: var(--mono); font-size: 12px; }

/* ── Pagination ── */
.adm-pagination {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 14px 20px;
    border-top: 1px solid var(--border);
}
.adm-page-btn {
    min-width: 32px; height: 32px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: var(--radius);
    font-size: 13px;
    color: var(--text-2);
    border: 1px solid transparent;
    transition: all var(--transition);
    padding: 0 8px;
}
.adm-page-btn:hover { background: var(--surface-3); color: var(--text-1); border-color: var(--border); }
.adm-page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ── Text helpers ── */
.adm-text-muted { color: var(--text-3); }
.adm-text-sm    { font-size: 12px; }

/* ── Modal ── */
.adm-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none;
    transition: opacity .2s ease;
}
.adm-modal-overlay.active { opacity: 1; pointer-events: all; }
.adm-modal {
    background: var(--surface);
    border: 1px solid var(--border-2);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 480px;
    margin: 20px;
    box-shadow: var(--shadow-lg);
    transform: translateY(16px);
    transition: transform .2s ease;
}
.adm-modal-overlay.active .adm-modal { transform: translateY(0); }
.adm-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
}
.adm-modal-title { font-size: 15px; font-weight: 600; color: var(--text-1); }
.adm-modal-close {
    background: none; border: none; cursor: pointer;
    color: var(--text-3); font-size: 16px; padding: 4px;
    transition: color var(--transition); line-height: 1;
}
.adm-modal-close:hover { color: var(--text-1); }
.adm-modal-body { padding: 20px; }
.adm-modal-section { margin-bottom: 20px; }
.adm-modal-section:last-child { margin-bottom: 0; }
.adm-modal-section-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-3);
    margin-bottom: 10px;
}

/* ── Forms ── */
.adm-inline-form { display: flex; align-items: center; gap: 8px; }
.adm-stack-form { display: flex; flex-direction: column; gap: 10px; }
.adm-form-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 0;
}

/* ── Action group ── */
.adm-action-group { display: flex; align-items: center; gap: 4px; }

/* ── Log action ── */
.adm-log-action { font-family: var(--mono); font-size: 12px; }

/* ── Servers page ── */
.adm-card-mode { margin-bottom: 16px; }
.adm-mode-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.adm-server-count {
    font-size: 12px;
    color: var(--text-3);
    font-family: var(--mono);
}
.adm-servers-list { padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }
.adm-servers-empty { padding: 16px !important; }
.adm-server-row {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 8px 12px;
    transition: border-color var(--transition);
}
.adm-server-row:hover { border-color: var(--border-2); }
.adm-server-row-handle {
    color: var(--text-3);
    cursor: grab;
    font-size: 14px;
    padding: 0 4px;
    flex-shrink: 0;
    user-select: none;
}
.adm-server-fields {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    flex-wrap: wrap;
}
.adm-server-fields .adm-input { min-width: 0; }

/* ── Toggle checkbox ── */
.adm-toggle-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    color: var(--text-2);
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
.adm-toggle-label input[type="checkbox"] {
    width: 15px; height: 15px;
    accent-color: var(--accent);
    cursor: pointer;
}
</style>
</head>
<body>
<div class="adm-layout">

<!-- Sidebar -->
<aside class="adm-sidebar">
    <div class="adm-sidebar-logo">
        <img src="<?= SITE_URL ?>/assets/logo.png" alt="CSHunter"
             style="height:44px;width:auto;display:block;filter:brightness(0) invert(1);opacity:.9;">
        <div class="adm-sidebar-logo-sub" style="margin-top:3px;">Admin Panel</div>
    </div>

    <nav class="adm-nav">
        <div class="adm-nav-section">
            <div class="adm-nav-label">Огляд</div>
            <a href="index.php" class="adm-nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                Dashboard
            </a>
        </div>

        <div class="adm-nav-section">
            <div class="adm-nav-label">Контент</div>
            <a href="servers.php" class="adm-nav-item <?= $activePage === 'servers' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="2" width="20" height="8" rx="2"/>
                    <rect x="2" y="14" width="20" height="8" rx="2"/>
                    <line x1="6" y1="6" x2="6.01" y2="6"/>
                    <line x1="6" y1="18" x2="6.01" y2="18"/>
                </svg>
                Сервери
            </a>
            <a href="items.php" class="adm-nav-item <?= $activePage === 'items' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                    <line x1="12" y1="12" x2="12" y2="16"/>
                    <line x1="10" y1="14" x2="14" y2="14"/>
                </svg>
                Предмети
            </a>
        </div>

        <div class="adm-nav-section">
            <div class="adm-nav-label">Гравці</div>
            <a href="users.php" class="adm-nav-item <?= $activePage === 'users' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Користувачі
            </a>
        </div>

        <div class="adm-nav-section">
            <div class="adm-nav-label">Система</div>
            <a href="logs.php" class="adm-nav-item <?= $activePage === 'logs' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                Журнал дій
            </a>
            <a href="bans.php" class="adm-nav-item <?= $activePage === 'bans' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                </svg>
                Бани
            </a>
            <a href="servers_live.php" class="adm-nav-item <?= $activePage === 'servers_live' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
                Сервери Live
            </a>
            <a href="<?= SITE_URL ?>/" target="_blank" class="adm-nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                    <polyline points="15 3 21 3 21 9"/>
                    <line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
                Відкрити сайт ↗
            </a>
        </div>
    </nav>

    <div class="adm-sidebar-footer">
        <div class="adm-sidebar-user">
            <img src="<?= htmlspecialchars($admin['avatar_url'] ?? '') ?>" alt="">
            <div>
                <div class="adm-sidebar-user-name"><?= htmlspecialchars(mb_substr($admin['steam_name'] ?? 'Admin', 0, 18)) ?></div>
                <div class="adm-sidebar-user-role">Admin</div>
            </div>
        </div>
    </div>
</aside>

<!-- Main -->
<main class="adm-main">
    <header class="adm-header">
        <div class="adm-breadcrumb">
            admin / <span><?= htmlspecialchars($title) ?></span>
        </div>
        <div class="adm-header-spacer"></div>
        <a href="<?= SITE_URL ?>/" target="_blank" class="adm-header-site-link">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/>
                <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            cshunter.com
        </a>
    </header>

    <div class="adm-content">
    <?php
}

function adminLayoutEnd(): void {
    ?>
    </div><!-- .adm-content -->
</main>
</div><!-- .adm-layout -->
</body>
</html>
    <?php
}
