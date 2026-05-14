<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Product Store') ?></title>
    <style>
        :root { --bg: #0f1419; --card: #1a2332; --text: #e7ecf3; --muted: #8b9cb3; --accent: #3d8bfd; --danger: #e05252; --ok: #3ecf8e; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, Segoe UI, Roboto, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; min-height: 100vh; }
        body.page-light { background: var(--bg); }
        .topbar { border-bottom: 1px solid rgba(255,255,255,.06); background: rgba(12,16,22,.92); position: sticky; top: 0; z-index: 10; backdrop-filter: blur(8px); }
        .nav-wrap { max-width: 1380px; margin: 0 auto; padding: 0.75rem 1.25rem; display: flex; gap: 1rem; align-items: center; justify-content: space-between; }
        .brand { color: var(--text); text-decoration: none; font-weight: 650; letter-spacing: 0.01em; }
        .nav { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
        .nav a, .nav summary { color: var(--text); text-decoration: none; font-size: 0.9rem; padding: 0.45rem 0.65rem; border-radius: 6px; cursor: pointer; list-style: none; }
        .nav a:hover, .nav summary:hover { background: rgba(255,255,255,.08); }
        .nav details { position: relative; }
        .nav summary::-webkit-details-marker { display: none; }
        .nav summary::after { content: "▾"; color: var(--muted); margin-left: 0.35rem; font-size: 0.75rem; }
        .nav details:not([open]) > .dropdown { display: none; }
        .nav details:hover > .dropdown, .nav details:focus-within > .dropdown, .nav details[open] > .dropdown { display: block; }
        .dropdown { min-width: 210px; position: absolute; right: 0; top: 100%; background: var(--card); border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 0.35rem; box-shadow: 0 16px 35px rgba(0,0,0,.28); }
        .dropdown a { display: block; white-space: nowrap; color: var(--text); }
        .dropdown form { margin: 0; }
        .nav button.nav-link { width: 100%; display: block; text-align: left; background: transparent; color: var(--text); font-weight: 400; padding: 0.45rem 0.65rem; border-radius: 6px; }
        .nav button.nav-link:hover { background: rgba(255,255,255,.08); }
        @media (max-width: 620px) { .nav-wrap { align-items: flex-start; flex-direction: column; } .dropdown { left: 0; right: auto; } }
        .wrap { max-width: 640px; margin: 0 auto; padding: 2rem 1.25rem; }
        .wrap-wide { max-width: 1380px; }
        h1 { font-size: 1.35rem; font-weight: 600; margin: 0 0 0.5rem; }
        p.lead { color: var(--muted); margin: 0 0 1.5rem; font-size: 0.95rem; }
        .card { background: var(--card); color: var(--text); border-radius: 10px; padding: 1.5rem; border: 1px solid rgba(255,255,255,.06); margin-bottom: 1rem; }
        .card:last-of-type { margin-bottom: 0; }
        label { display: block; font-size: 0.8rem; color: var(--muted); margin: 0.75rem 0 0.25rem; }
        input:not([type="checkbox"]):not([type="radio"]), select, textarea { width: 100%; padding: 0.55rem 0.65rem; border-radius: 6px; border: 1px solid rgba(255,255,255,.12); background: #0c1016; color: var(--text); font-size: 0.95rem; }
        .readonly-field { width: 100%; padding: 0.55rem 0.65rem; border-radius: 6px; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.04); color: var(--text); font-size: 0.95rem; }
        input[type="checkbox"], input[type="radio"] { width: 1.125rem; height: 1.125rem; margin: 0; padding: 0; flex-shrink: 0; accent-color: var(--accent); cursor: pointer; }
        label.field-check { display: flex; align-items: flex-start; gap: 0.65rem; margin: 0.6rem 0 0; font-size: 0.9rem; color: var(--text); line-height: 1.45; cursor: pointer; }
        label.field-check:first-of-type { margin-top: 0; }
        label.field-check .field-check-text { flex: 1; min-width: 0; color: var(--text); }
        label.field-check .field-check-text code { vertical-align: baseline; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .row-host-port { grid-template-columns: 1fr minmax(5rem, 6.75rem); align-items: end; }
        .row-db-prefix { grid-template-columns: 1fr minmax(6.5rem, 9rem); align-items: start; }
        .row-user-pass { grid-template-columns: 1fr 1fr; align-items: end; }
        .field-password { position: relative; width: 100%; }
        .field-password > input { padding-right: 5.75rem; position: relative; z-index: 1; }
        .field-password > input::-ms-reveal, .field-password > input::-ms-clear { display: none; }
        @media (max-width: 520px) { .row { grid-template-columns: 1fr; } }
        .actions { margin-top: 1.25rem; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
        button, .btn { cursor: pointer; border: 0; border-radius: 6px; padding: 0.6rem 1rem; font-size: 0.9rem; font-weight: 500; text-decoration: none; display: inline-block; }
        button.password-toggle {
            position: absolute;
            right: 0.35rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            min-width: 4.35rem;
            background: #263245;
            color: var(--text);
            border: 1px solid rgba(255,255,255,.14);
            padding: 0.35rem 0.6rem;
            font-size: 0.75rem;
            border-radius: 4px;
            line-height: 1.2;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        button.password-toggle:hover { background: #30405a; }
        button.password-toggle:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
        button.password-toggle:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-secondary { background: rgba(255,255,255,.08); color: var(--text); }
        .btn-danger { background: var(--danger); color: #fff; }
        a.btn-primary, .prose a.btn-primary { color: #fff; }
        a.btn-secondary, .prose a.btn-secondary { color: var(--text); }
        a.btn-danger, .prose a.btn-danger { color: #fff; }
        .btn.disabled, .btn[aria-disabled="true"] { opacity: 0.45; pointer-events: none; cursor: not-allowed; }
        .err { background: rgba(224,82,82,.12); border: 1px solid rgba(224,82,82,.35); color: #ffb4b4; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .ok { background: rgba(62,207,142,.12); border: 1px solid rgba(62,207,142,.35); color: #b8f5d3; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .hint { font-size: 0.8rem; color: var(--muted); margin-top: 0.35rem; }
        code { font-size: 0.85em; background: rgba(0,0,0,.35); padding: 0.1em 0.35em; border-radius: 4px; }
        .prose h2 { font-size: 1rem; font-weight: 600; margin: 0 0 0.5rem; color: var(--text); }
        .prose p { color: var(--muted); margin: 0.5rem 0; }
        .prose pre { background: #0c1016; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 0.85rem 1rem; overflow-x: auto; font-size: 0.82rem; color: var(--text); margin: 0.75rem 0; }
        .prose a { color: var(--accent); text-decoration: none; }
        .prose a:hover { text-decoration: underline; }
        .site-hero { position: relative; background: #e9f0f3; border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 1.65rem 2rem; min-height: 13rem; margin-bottom: 1rem; box-shadow: 0 18px 45px rgba(0,0,0,.18); display: flex; align-items: center; overflow: hidden; }
        .site-hero::after { content: ""; position: absolute; inset: 0; z-index: 1; background: linear-gradient(90deg, rgba(12,16,22,.88), rgba(12,16,22,.62), rgba(12,16,22,.1)); pointer-events: none; }
        .site-hero-art { position: absolute; inset: 0; z-index: 0; width: 100%; height: 100%; object-fit: cover; object-position: center right; }
        .site-hero-copy { position: relative; z-index: 2; min-width: 0; max-width: 46rem; }
        .site-hero h1 { font-size: 2rem; line-height: 1.15; margin-bottom: 0.5rem; }
        .site-hero p { color: #c0cad8; max-width: 44rem; margin: 0; }
        .home-actions { display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
        .home-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); gap: 0.75rem; align-items: stretch; margin-bottom: 0.75rem; }
        .home-grid .card, .home-stat-grid .card { margin-bottom: 0; }
        .home-card { display: flex; flex-direction: column; gap: 0.55rem; height: 100%; padding: 1rem; }
        .home-card h2, .home-card p { margin: 0; }
        .home-card h2 { font-size: 1rem; }
        .home-card p { color: var(--muted); }
        .home-card > .home-actions { margin-top: auto; }
        .home-card-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .home-card-head a { color: var(--accent); text-decoration: none; font-size: 0.85rem; white-space: nowrap; }
        .home-card-head a:hover { text-decoration: underline; }
        .home-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.75rem; align-items: stretch; margin-bottom: 0.75rem; }
        .home-stat-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.55rem; align-items: stretch; }
        .home-stat { display: grid; align-content: start; min-height: 4.25rem; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.6rem 0.75rem; margin-bottom: 0; }
        body.page-light .home-stat.card { background: var(--card); border-color: rgba(255,255,255,.06); }
        .home-stat span { display: block; color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 0.25rem; }
        .home-stat strong { display: block; color: var(--text); font-size: 1.35rem; line-height: 1.05; }
        .home-link-list { display: grid; gap: 0.4rem; }
        .home-link-list a { display: grid; gap: 0.15rem; color: var(--text); text-decoration: none; padding: 0.55rem 0.65rem; border-radius: 9px; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.06); }
        .home-link-list a:hover { background: rgba(61,139,253,.12); border-color: rgba(61,139,253,.25); text-decoration: none; }
        .home-link-list span { color: var(--muted); font-size: 0.85rem; }
        .home-online-members { display: flex; flex-wrap: wrap; gap: 0.55rem; }
        .home-online-member-name { display: inline-flex; align-items: center; color: var(--text); padding: 0.35rem 0.55rem; border-radius: 999px; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); font-size: 0.85rem; }
        @media (max-width: 760px) { .site-hero { padding: 1.35rem 1.25rem; min-height: 12rem; } .site-hero::after { background: linear-gradient(90deg, rgba(12,16,22,.92), rgba(12,16,22,.68)); } .site-hero-art { object-position: center right; } }
        .dashboard-shell { display: grid; grid-template-columns: 18rem minmax(0, 1fr); gap: 1rem; align-items: start; }
        .dashboard-sidebar { position: sticky; top: 5.25rem; display: grid; gap: 0.75rem; }
        .dashboard-sidebar-heading { background: var(--card); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 0.85rem; font-weight: 700; color: var(--text); }
        .dashboard-sidebar-type { color: #b9d6ff; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; margin: 0.25rem 0 -0.35rem; padding-left: 0.15rem; }
        .dashboard-sidebar-section { background: var(--card); border: 1px solid rgba(255,255,255,.06); border-radius: 10px; padding: 0.85rem; }
        .dashboard-sidebar-title { color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; margin: 0 0 0.55rem; }
        .dashboard-sidebar-nav { display: grid; gap: 0.35rem; }
        .dashboard-sidebar-nav a { color: var(--text); text-decoration: none; padding: 0.5rem 0.65rem; border-radius: 7px; font-size: 0.9rem; background: rgba(255,255,255,.025); border: 1px solid transparent; }
        .dashboard-sidebar-nav a:hover, .dashboard-sidebar-nav a.active { background: rgba(61,139,253,.13); border-color: rgba(61,139,253,.28); color: #b9d6ff; }
        .dashboard-sidebar-disabled { color: var(--muted); padding: 0.5rem 0.65rem; border-radius: 7px; font-size: 0.85rem; background: rgba(255,255,255,.018); border: 1px dashed rgba(255,255,255,.08); }
        .dashboard-main { min-width: 0; }
        .dashboard-main > h1 { background: var(--card); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; color: var(--text); padding: 0.85rem 1rem; margin-bottom: 1rem; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr)); gap: 1rem; }
        .dashboard-grid .card { margin-bottom: 0; }
        .analytics-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
        .analytics-head h2 { margin: 0 0 0.2rem; font-size: 1.05rem; }
        .analytics-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
        .analytics-stat { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.85rem; }
        .analytics-stat span { display: block; color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 0.25rem; }
        .analytics-stat strong { display: block; color: var(--text); font-size: 1.45rem; line-height: 1.1; }
        .analytics-chart { display: grid; grid-template-columns: repeat(14, minmax(1.8rem, 1fr)); gap: 0.45rem; align-items: end; min-height: 13rem; padding: 0.85rem; border-radius: 10px; background: rgba(12,16,22,.45); border: 1px solid rgba(255,255,255,.07); overflow-x: auto; }
        .analytics-bar-item { display: grid; grid-template-rows: 1fr auto; gap: 0.45rem; min-width: 2.25rem; height: 11rem; align-items: end; }
        .analytics-bar { display: flex; align-items: flex-start; justify-content: center; height: var(--bar-height); min-height: 0.35rem; border-radius: 8px 8px 3px 3px; background: linear-gradient(180deg, rgba(62,207,142,.9), rgba(61,139,253,.75)); box-shadow: 0 10px 24px rgba(61,139,253,.14); }
        .analytics-bar span { color: #fff; font-size: 0.68rem; font-weight: 800; padding-top: 0.25rem; }
        .analytics-bar-item small { color: var(--muted); font-size: 0.68rem; text-align: center; white-space: nowrap; }
        .analytics-pages { margin-top: 1rem; display: grid; gap: 0.45rem; }
        .analytics-pages h3 { margin: 0 0 0.2rem; color: var(--muted); font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .analytics-page-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.75rem; align-items: center; padding: 0.6rem 0.75rem; border-radius: 8px; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.06); }
        .analytics-page-row code { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .analytics-page-row span { color: var(--muted); font-size: 0.85rem; white-space: nowrap; }
        .module-toggle-list { display: grid; gap: 0.65rem; margin-top: 1rem; }
        .module-toggle { display: flex; align-items: flex-start; gap: 0.75rem; margin: 0; padding: 0.85rem; border: 1px solid rgba(255,255,255,.08); border-radius: 10px; background: rgba(255,255,255,.025); cursor: pointer; }
        .module-toggle:hover { background: rgba(255,255,255,.045); }
        .module-toggle-body { display: grid; gap: 0.25rem; min-width: 0; }
        .module-toggle-title { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; color: var(--text); font-weight: 700; }
        .module-toggle-description { color: var(--muted); font-size: 0.85rem; }
        .cang-profile-table-wrap { display: grid; gap: 0.35rem; margin-top: 1rem; overflow-x: auto; }
        .cang-profile-table-head,
        .cang-profile-row {
            display: grid;
            grid-template-columns: minmax(7.5rem, 1.2fr) minmax(7rem, 1fr) minmax(8.5rem, 1.05fr) minmax(4.75rem, 0.7fr) 2.75rem 5.5rem 5.5rem 3.75rem;
            gap: 0.3rem;
            align-items: center;
            min-width: 52rem;
        }
        .cang-profile-table-head { color: var(--muted); font-size: 0.65rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 0.45rem 0.2rem; }
        .cang-profile-row { background: var(--card); border: 1px solid rgba(255,255,255,.07); border-radius: 8px; padding: 0.45rem; }
        .cang-profile-cell { min-width: 0; font-size: 0.78rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cang-profile-cell code { display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.72rem; vertical-align: middle; }
        .cang-profile-name strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cang-profile-center { text-align: center; font-weight: 800; color: #b9d6ff; }
        .cang-profile-row .status-pill { width: 100%; padding: 0.2rem 0.4rem; font-size: 0.68rem; }
        .cang-profile-row .btn { width: 100%; min-height: 1.75rem; padding: 0.3rem 0.45rem; font-size: 0.75rem; text-align: center; }
        .profile-head { display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; }
        .user-avatar, .user-avatar-fallback { width: 5.25rem; height: 5.25rem; border-radius: 50%; border: 2px solid rgba(255,255,255,.14); flex-shrink: 0; }
        .user-avatar { display: block; object-fit: cover; background: #0c1016; }
        .user-avatar-fallback { display: grid; place-items: center; background: linear-gradient(135deg, rgba(61,139,253,.9), rgba(62,207,142,.65)); color: #fff; font-size: 2rem; font-weight: 700; }
        .profile-head h2 { margin-bottom: 0.15rem; }
        .role-list-head { display: flex; gap: 1rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .role-list-head-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .role-list-columns, .role-list-row { display: grid; grid-template-columns: minmax(12rem, 1.4fr) 4.5rem 6.75rem 6.75rem 18rem; gap: 0.75rem; align-items: center; }
        .cang-list-columns, .cang-list-row { grid-template-columns: minmax(14rem, 1.4fr) minmax(13rem, 1fr) 4.5rem 7.5rem 6.75rem 7rem; }
        .role-list-columns { color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,.08); }
        .role-list-row { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.8rem 0.75rem; margin-top: 0.65rem; }
        .role-list-row:hover { background: rgba(255,255,255,.045); }
        .role-list-main p { margin-bottom: 0; }
        .role-list-level { display: flex; align-items: center; justify-content: center; }
        .role-level-badge { display: inline-grid; place-items: center; min-width: 2.25rem; height: 2.25rem; border-radius: 999px; background: rgba(61,139,253,.16); color: #b9d6ff; border: 1px solid rgba(61,139,253,.35); font-weight: 700; }
        .status-pill { display: inline-flex; align-items: center; justify-content: center; width: 6.25rem; border-radius: 999px; padding: 0.25rem 0.55rem; font-size: 0.75rem; font-weight: 700; border: 1px solid rgba(255,255,255,.12); }
        .status-active { background: rgba(62,207,142,.13); color: #b8f5d3; border-color: rgba(62,207,142,.35); }
        .status-inactive { background: rgba(139,156,179,.12); color: #c0cad8; border-color: rgba(139,156,179,.28); }
        .status-system { background: rgba(61,139,253,.13); color: #b9d6ff; border-color: rgba(61,139,253,.32); }
        .status-custom { background: rgba(255,255,255,.08); color: var(--text); }
        .role-list-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; align-items: stretch; }
        .role-list-actions form { margin: 0; display: contents; }
        .role-list-actions .btn { width: 100%; min-width: 0; min-height: 2.35rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        .user-list-columns, .user-list-row { display: grid; grid-template-columns: minmax(16rem, 1.45fr) minmax(8rem, 0.8fr) 6.75rem minmax(14rem, 1fr) 15rem; gap: 0.75rem; align-items: center; }
        .user-list-columns { color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,.08); }
        .user-list-row { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.8rem 0.75rem; margin-top: 0.65rem; }
        .user-list-row:hover { background: rgba(255,255,255,.045); }
        .user-list-account { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
        .user-list-row .role-level-badge { width: 100%; height: auto; min-height: 2.25rem; padding: 0.35rem 0.7rem; text-align: center; }
        .user-role-form { display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: center; margin: 0; }
        .user-role-form .btn { white-space: nowrap; min-height: 2.35rem; }
        .user-list-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; align-items: stretch; }
        .user-list-actions form { margin: 0; display: contents; }
        .user-list-actions .btn { width: 100%; min-width: 0; min-height: 2.35rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        .member-list-columns, .member-list-row { display: grid; grid-template-columns: minmax(13rem, 1.2fr) minmax(13rem, 1fr) minmax(8rem, 0.8fr) 6.75rem 10rem; gap: 0.75rem; align-items: center; }
        .member-list-columns { color: var(--muted); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0 0.75rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,.08); margin-top: 1rem; }
        .member-list-row { background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 0.8rem 0.75rem; margin-top: 0.65rem; }
        .member-list-row:hover { background: rgba(255,255,255,.045); }
        .member-list-user { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
        .member-list-profile-link { display: inline-flex; align-items: center; gap: 0.65rem; color: var(--text); text-decoration: none; }
        .member-list-profile-link:hover { color: var(--accent); text-decoration: none; }
        .member-list-avatar, .member-list-avatar-fallback { width: 2.35rem; height: 2.35rem; border-radius: 50%; border: 1px solid rgba(255,255,255,.14); flex-shrink: 0; }
        .member-list-avatar { display: block; object-fit: cover; background: #0c1016; }
        .member-list-avatar-fallback { display: grid; place-items: center; background: linear-gradient(135deg, rgba(61,139,253,.9), rgba(62,207,142,.65)); color: #fff; font-size: 0.95rem; font-weight: 700; }
        .member-list-row .role-level-badge { width: 100%; height: auto; min-height: 2.25rem; padding: 0.35rem 0.7rem; text-align: center; }
        .member-list-row .btn { width: 100%; min-height: 2.35rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        .member-list-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(4.25rem, 1fr)); gap: 0.4rem; align-items: stretch; }
        .content-list-table-wrap { overflow-x: auto; }
        .content-list-table { width: 100%; border-collapse: separate; border-spacing: 0 0.45rem; table-layout: fixed; }
        .content-list-table th { color: var(--muted); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; text-align: left; padding: 0 0.55rem 0.3rem; border-bottom: 1px solid rgba(255,255,255,.08); }
        .content-list-table th:nth-child(1), .content-list-table td:nth-child(1) { width: 44%; }
        .content-list-table th:nth-child(2), .content-list-table td:nth-child(2) { width: 6.75rem; text-align: center; }
        .content-list-table th:nth-child(3), .content-list-table td:nth-child(3) { width: 6.75rem; text-align: center; }
        .content-list-table th:nth-child(4), .content-list-table td:nth-child(4) { width: 9rem; text-align: center; }
        .content-list-table th:nth-child(5), .content-list-table td:nth-child(5) { width: 13.5rem; text-align: center; }
        .content-list-table td { background: rgba(255,255,255,.025); border-top: 1px solid rgba(255,255,255,.07); border-bottom: 1px solid rgba(255,255,255,.07); padding: 0.55rem; vertical-align: middle; }
        .content-list-table td:first-child { border-left: 1px solid rgba(255,255,255,.07); border-radius: 10px 0 0 10px; }
        .content-list-table td:last-child { border-right: 1px solid rgba(255,255,255,.07); border-radius: 0 10px 10px 0; }
        .content-list-table tr:hover td { background: rgba(255,255,255,.045); }
        .content-list-table .content-list-group-row td { background: transparent; border: 0; padding: 0.35rem 0.55rem 0.05rem; border-radius: 0; }
        .content-list-table .content-list-group-row:hover td { background: transparent; }
        .content-list-group-row span { display: inline-flex; align-items: center; border-radius: 999px; padding: 0.25rem 0.65rem; color: #b9d6ff; background: rgba(61,139,253,.13); border: 1px solid rgba(61,139,253,.28); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
        .content-list-main p { margin-bottom: 0; }
        .content-list-status, .content-list-nav { min-width: 0; text-align: center; }
        .content-list-nav p { margin: 0.35rem 0 0; max-width: 100%; }
        .content-list-nav code { display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .content-list-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(3.85rem, 1fr)); gap: 0.35rem; align-items: stretch; }
        .content-list-actions .btn { width: 100%; min-width: 0; min-height: 2rem; padding: 0.45rem 0.55rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        .content-pagination { margin-top: 1rem; display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; color: var(--muted); font-size: 0.85rem; }
        .pagination-links { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
        .pagination-links .btn { min-width: 2.25rem; min-height: 2rem; padding: 0.4rem 0.65rem; text-align: center; }
        .pagination-links .btn.active { background: var(--accent); color: #fff; }
        .pagination-links .btn.disabled { opacity: 0.45; pointer-events: none; }
        .content-body p, .content-body li, .content-body blockquote { color: var(--text); }
        .content-body img { max-width: 100%; height: auto; border-radius: 8px; }
        .content-body blockquote { margin: 0.8rem 0; padding-left: 1rem; border-left: 3px solid var(--accent); color: var(--muted); }
        .content-body hr { border: 0; border-top: 1px solid rgba(255,255,255,.12); margin: 1.25rem 0; }
        .mailbox-shell { display: grid; grid-template-columns: 14rem minmax(0, 1fr); gap: 1rem; align-items: start; }
        .mailbox-sidebar { position: sticky; top: 5.25rem; display: grid; gap: 0.5rem; margin-bottom: 0; }
        .mailbox-folder { display: flex; align-items: center; justify-content: space-between; color: var(--text); text-decoration: none; padding: 0.65rem 0.75rem; border-radius: 8px; background: rgba(255,255,255,.025); border: 1px solid transparent; }
        .mailbox-folder:hover, .mailbox-folder.active { background: rgba(61,139,253,.13); border-color: rgba(61,139,253,.28); color: #b9d6ff; text-decoration: none; }
        .mailbox-main { min-width: 0; }
        .mailbox-list { display: grid; gap: 0.45rem; }
        .mailbox-group { margin: 0.4rem 0 0.1rem; color: #b9d6ff; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .mailbox-row { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(12rem, 15rem); gap: 0.75rem; align-items: center; padding: 0.75rem; border: 1px solid rgba(255,255,255,.07); border-radius: 10px; background: rgba(255,255,255,.025); }
        .mailbox-row:hover { background: rgba(255,255,255,.045); }
        .mailbox-row-main { display: grid; gap: 0.2rem; min-width: 0; color: var(--text); text-decoration: none; }
        .mailbox-row-main:hover { color: var(--text); text-decoration: none; }
        .mailbox-row-subject { font-weight: 750; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mailbox-row-meta { color: var(--muted); font-size: 0.82rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mailbox-row-side { display: grid; justify-items: end; gap: 0.35rem; min-width: 8rem; }
        .mailbox-row-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(3.85rem, 1fr)); gap: 0.35rem; }
        .mailbox-row-actions .btn { min-height: 2rem; padding: 0.45rem 0.55rem; text-align: center; display: inline-flex; align-items: center; justify-content: center; }
        .card,
        .home-stat.card,
        .dashboard-sidebar-section,
        .analytics-stat,
        .analytics-page-row,
        .module-toggle,
        .role-list-row,
        .user-list-row,
        .member-list-row,
        .content-list-table td,
        .mailbox-folder,
        .mailbox-row {
            background: var(--card);
            color: var(--text);
        }
        @media (max-width: 760px) { .dashboard-shell, .mailbox-shell { grid-template-columns: 1fr; } .dashboard-sidebar, .mailbox-sidebar { position: static; } .mailbox-row { grid-template-columns: 1fr; } .mailbox-row-side { justify-items: start; } .role-list-columns, .user-list-columns, .member-list-columns { display: none; } .role-list-head, .role-list-row, .user-list-row, .member-list-row { display: flex; align-items: flex-start; flex-direction: column; } .role-list-actions, .user-list-actions, .user-role-form, .content-list-actions, .mailbox-row-actions { width: 100%; } }
        .site-footer { max-width: 1040px; margin: 0 auto; padding: 0 1.25rem 1.5rem; color: var(--muted); font-size: 0.75rem; text-align: center; }
        .site-footer code { color: var(--text); }
        .meta { font-size: 0.75rem; color: var(--muted); text-align: center; margin-top: 1.5rem; }
    </style>
</head>
<body class="<?= ! empty($lightPage) ? 'page-light' : '' ?>">
<?php
helper('form');

$requestSegments = service('request')->getUri()->getSegments();
$isInstallRoute = ($requestSegments[0] ?? '') === 'install';
$installed = \App\Libraries\InstallationState::isInstalled();
$webName = 'Change Name';
if ($installed && ! $isInstallRoute) {
    try {
        $webName = (new \App\Libraries\WebSettings())->homeSettings()['web_name'];
    } catch (\Throwable) {
        $webName = 'Change Name';
    }
}
$webName = trim((string) $webName) ?: 'Change Name';
$memberUserId = session()->get('member_user_id');
$memberUsername = (string) (session()->get('member_username') ?? '');
$memberLoggedIn = is_numeric($memberUserId);
$memberCanManageRoles = $memberLoggedIn && (bool) session()->get('member_can_manage_roles');
if ($memberLoggedIn && session()->get('member_can_manage_roles') === null) {
    $memberRole = (string) (session()->get('member_role') ?? '');
    $memberCanManageRoles = $memberRole !== '' && (new \App\Libraries\RoleService())->isAdministrator($memberRole);
}
$publicContentNavItems = [];
$publicContentEnabled = true;
if ($isInstallRoute) {
    $publicContentEnabled = false;
} elseif ($installed) {
    try {
        $publicContentEnabled = (new \App\Libraries\ModuleSettings())->isEnabled(\App\Libraries\ModuleSettings::CONTENT_PUBLIC);
    } catch (\Throwable) {
        $publicContentEnabled = true;
    }
}
if ($installed && ! $isInstallRoute && $publicContentEnabled) {
    try {
        $db = \App\Libraries\AppDatabase::connection();
        if ($db->tableExists('public_contents')) {
            $fields = $db->getFieldNames('public_contents');
            if (
                in_array('show_in_nav', $fields, true)
                && in_array('nav_label', $fields, true)
                && in_array('nav_order', $fields, true)
            ) {
                $publicContentNavItems = $db->table('public_contents')
                    ->select('title, slug, nav_label, nav_order')
                    ->where('status', 'published')
                    ->where('show_in_nav', true)
                    ->groupStart()
                    ->where('published_at', null)
                    ->orWhere('published_at <=', date('Y-m-d H:i:s'))
                    ->groupEnd()
                    ->orderBy('nav_order', 'ASC')
                    ->orderBy('title', 'ASC')
                    ->get()
                    ->getResultArray();
            }
        }
    } catch (\Throwable) {
        $publicContentNavItems = [];
    }
}
?>
<header class="topbar">
    <div class="nav-wrap">
        <a class="brand" href="<?= esc(site_url('/')) ?>"><?= esc($webName) ?></a>
        <nav class="nav" aria-label="Main navigation">
            <a href="<?= esc(site_url('/')) ?>">Home</a>
            <?php foreach ($publicContentNavItems as $item) : ?>
                <a href="<?= esc(site_url('Content/Public/View/' . (string) $item['slug'])) ?>">
                    <?= esc((string) (($item['nav_label'] ?? '') ?: $item['title'])) ?>
                </a>
            <?php endforeach ?>
            <?php if ($installed) : ?>
                <details>
                    <summary><?= $memberLoggedIn ? esc($memberUsername !== '' ? $memberUsername : 'Account') : 'Member' ?></summary>
                    <div class="dropdown">
                        <?php if ($memberLoggedIn) : ?>
                            <a href="<?= esc(site_url('Member/User/MyProfile')) ?>">My Profile</a>
                            <a href="<?= esc(site_url('Member/List')) ?>">Member List</a>
                            <form method="post" action="<?= esc(site_url('Member/User/Logout')) ?>">
                                <?= csrf_field() ?>
                                <button class="nav-link" type="submit">Logout</button>
                            </form>
                        <?php else : ?>
                            <a href="<?= esc(site_url('Member/User/Login')) ?>">Login</a>
                            <a href="<?= esc(site_url('Member/User/Register')) ?>">Register</a>
                            <a href="<?= esc(site_url('Member/User/ForgotPassword')) ?>">Forgot Password</a>
                        <?php endif ?>
                    </div>
                </details>
                <?php if ($memberLoggedIn) : ?>
                    <a href="<?= esc(site_url('DashBoard/Index')) ?>">Dashboard</a>
                <?php endif ?>
            <?php endif ?>
        </nav>
    </div>
</header>
<div class="wrap <?= ! empty($wideLayout) ? 'wrap-wide' : '' ?>">
    <?= $this->renderSection('main') ?>
</div>
<footer class="site-footer">
    Environment: <code><?= esc(ENVIRONMENT) ?></code>
    · Rendered in <code>{elapsed_time}</code> seconds
    · Memory: <code>{memory_usage}</code> MB
</footer>
<script>
(function () {
    document.querySelectorAll('.nav details').forEach(function (details) {
        details.addEventListener('mouseenter', function () {
            details.open = true;
        });
        details.addEventListener('mouseleave', function () {
            details.open = false;
        });
        details.addEventListener('focusin', function () {
            details.open = true;
        });
        details.addEventListener('focusout', function () {
            window.setTimeout(function () {
                if (! details.contains(document.activeElement)) {
                    details.open = false;
                }
            }, 0);
        });
    });
})();
</script>
</body>
</html>
