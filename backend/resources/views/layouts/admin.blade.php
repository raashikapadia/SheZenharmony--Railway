<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Administration') &middot; SheZen Harmony</title>
    <style>
        :root{color-scheme:light;--ink:#102f34;--muted:#617276;--teal:#103f41;--teal2:#205b5c;--accent:#147b78;--canvas:#f8f8f3;--surface:#fff;--line:#d7dfdc;--danger:#c63d43;font-family:"Segoe UI",Inter,system-ui,sans-serif;color:var(--ink);background:var(--canvas)}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--canvas)}a{color:inherit}h1,h2,h3,.brand-title,.metric{font-family:Georgia,"Times New Roman",serif}h1,h2,h3,p{margin-top:0}button,input,select,textarea{font:inherit}
        .admin-shell{min-height:100vh;display:grid;grid-template-columns:250px minmax(0,1fr)}.sidebar{position:sticky;top:0;height:100vh;overflow-y:auto;padding:24px 14px;color:#eef8f6;background:linear-gradient(180deg,#173e62 0%,#102f50 100%)}.brand-block{padding:0 10px 28px;border-bottom:1px solid rgba(255,255,255,.12);margin-bottom:22px}.brand-mark{display:grid;width:52px;height:52px;place-items:center;margin-bottom:8px;color:#eef8f6}.brand-mark svg{width:52px;height:52px;stroke-width:1.35}.brand-title{font-size:1.35rem;font-weight:700;line-height:1.03;letter-spacing:-.02em}.brand-subtitle{margin-top:6px;color:#b9d6e5;font-size:.72rem;letter-spacing:.05em;text-transform:uppercase}.nav-section{margin:0 0 18px}.nav-label{padding:0 10px 7px;color:#92b6cf;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase}.side-link{display:flex;align-items:center;gap:11px;min-height:38px;margin:2px 0;padding:8px 10px;border-radius:10px;color:#eff8f7;text-decoration:none;font-size:.82rem;transition:.18s}.side-link:hover{background:rgba(255,255,255,.1);transform:translateX(2px)}.side-link.active{background:linear-gradient(90deg,#3384d2,#2670bd);box-shadow:0 6px 14px rgba(0,0,0,.12)}.side-icon{display:inline-flex;width:20px;align-items:center;justify-content:center;color:#b9cce2}.side-icon svg{width:20px;height:20px;stroke-width:1.8}.side-signout{width:100%;border:0;cursor:pointer;background:transparent;text-align:left}
        .main-column{min-width:0;background:linear-gradient(135deg,#f7faff 0%,#fff 45%,#fff8fb 100%)}.page-topbar{min-height:72px;display:flex;justify-content:space-between;gap:24px;align-items:center;padding:13px 30px;background:#e8eef5;border-bottom:1px solid #d5e0eb}.page-topbar h1{margin:0;font-size:clamp(1.6rem,2.4vw,2rem);line-height:1.05;color:#082d32}.page-kicker{margin:5px 0 0;color:var(--muted);font-size:.9rem}.topbar-actions{display:flex;align-items:center;gap:12px}.topbar-search{display:flex;align-items:center;gap:9px;width:min(260px,30vw);padding:9px 12px;border:1px solid #dce5ee;border-radius:10px;color:#647996;background:#fff;font-size:.76rem}.topbar-search svg{width:20px;height:20px;stroke-width:1.8}.topbar-profile{display:flex;align-items:center;gap:9px;padding-left:8px;color:#152e57;font-size:.77rem;font-weight:700}.avatar{display:grid;width:34px;height:34px;place-items:center;border-radius:50%;color:#20345d;background:#dbe8f7;font-size:.78rem}.profile-chevron{width:16px;height:16px;stroke-width:1.8}.topbar-signout{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border:1px solid #dce5ee;border-radius:10px;color:#152e57;background:#fff;font-size:.76rem;font-weight:700;cursor:pointer;transition:background-color .18s ease,border-color .18s ease,color .18s ease,box-shadow .18s ease}.topbar-signout:hover{border-color:#d15b68;color:#a52e3d;background:#fff1f2;box-shadow:0 4px 12px rgba(165,46,61,.14)}.topbar-signout:focus-visible{outline:3px solid rgba(209,91,104,.28);outline-offset:2px}.topbar-signout svg{width:18px;height:18px;stroke-width:1.8}.content{position:relative;width:min(1500px,calc(100% - 48px));margin:25px auto 50px}.page-intro{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:18px}.page-intro h2{margin:5px 0 2px;font-size:2rem;letter-spacing:-.04em;color:#071d4a}.muted{color:var(--muted)}
        .cards{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr));gap:14px}.card,.panel{border:1px solid rgba(224,231,239,.8);background:rgba(255,255,255,.9);box-shadow:0 10px 26px rgba(49,76,117,.08)}.card{min-height:128px;padding:18px;border-radius:13px}.metric{margin-top:10px;color:#071d4a;font-size:2rem;line-height:1}.metric-note{margin-top:7px;color:var(--muted);font-size:.72rem}.panel{padding:20px;border-radius:13px}
        .button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:.7rem 1.1rem;border:0;border-radius:22px;color:#fff;background:var(--accent);box-shadow:0 3px 8px rgba(11,74,72,.18);font-weight:650;text-decoration:none;cursor:pointer}.button:hover{background:#188b86}.button-secondary{color:var(--ink);background:#fbfbf7;border:1px solid var(--line)}.button-secondary:hover{background:#f1f5f2}.button-danger{color:#fff;background:var(--danger)}.button-link{color:var(--accent);background:transparent;box-shadow:none}.actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center}
        .table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:24px;background:#fff}table{width:100%;border-collapse:collapse}th,td{padding:16px 18px;border-bottom:1px solid #e7ece9;text-align:left;vertical-align:middle}th{color:#496368;font-size:.76rem;letter-spacing:.05em;text-transform:uppercase}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:#fafcf9}.item-title{color:#062f34;font-weight:700}.badge{display:inline-flex;padding:5px 10px;border-radius:999px;color:#53666a;background:#edf1ef;font-size:.78rem;font-weight:650;text-transform:capitalize}.badge.active{color:#0c6865;background:#dff1ed}
        .form-panel{max-width:1100px}.form-section{padding:22px 0;border-top:1px solid var(--line)}.form-section:first-of-type{padding-top:0;border-top:0}label{display:block;margin:0 0 7px;color:#24474b;font-size:.85rem;font-weight:650}input[type=email],input[type=password],input[type=text],input[type=number],input[type=url],select,textarea{width:100%;padding:.78rem .9rem;border:1px solid #cfdad6;border-radius:13px;outline:none;background:#fff;color:var(--ink)}input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(20,123,120,.12)}textarea{min-height:7rem;resize:vertical}.field-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:16px}.remember{display:flex;align-items:center;gap:8px;margin:14px 0}.remember input{accent-color:var(--accent)}.status{padding:12px 16px;margin-bottom:18px;border:1px solid #b8dcd4;border-radius:14px;color:#125b58;background:#e6f4f0}.errors,.error{color:#9c2633}.errors{padding:14px 34px;border:1px solid #efc1c5;border-radius:14px;background:#fff1f2}.error{margin-top:5px;font-size:.86rem}
        .auth-wrap{display:grid;min-height:100vh;place-items:center;padding:24px;background:linear-gradient(135deg,var(--teal) 0 45%,var(--canvas) 45%)}.auth-card{width:min(440px,100%);padding:36px;border:1px solid var(--line);border-radius:28px;background:#fff;box-shadow:0 24px 60px rgba(3,47,48,.22)}.auth-card .brand{color:var(--accent);font-family:Georgia,serif;font-size:1.2rem;font-weight:700}.auth-card h1{margin:8px 0}
        @media(max-width:1000px){.admin-shell{grid-template-columns:220px minmax(0,1fr)}.cards{grid-template-columns:repeat(2,minmax(170px,1fr))}}@media(max-width:720px){.admin-shell{display:block}.sidebar{position:static;width:100%;height:auto;padding:20px}.brand-block{padding-bottom:14px}.sidebar nav{display:grid;grid-template-columns:repeat(2,1fr);gap:4px}.nav-section{display:contents}.nav-label{display:none}.page-topbar{min-height:auto;padding:14px 20px}.content{width:min(100% - 28px,1500px);margin-top:20px}.cards{grid-template-columns:1fr}.page-intro{flex-direction:column}.topbar-actions{display:none}}
    </style>
    <style>
        .content>form{max-width:1100px;padding:24px;border:1px solid var(--line);border-radius:28px;background:#fff;box-shadow:0 16px 34px rgba(25,64,59,.07)}
        .side-link.pending{color:#c7dcda}.side-link.pending::after{content:"Soon";margin-left:auto;color:#82b8b4;font-size:.62rem;letter-spacing:.05em;text-transform:uppercase}
        .card[data-coming-soon]{text-decoration:none;cursor:pointer;transition:transform .18s ease,border-color .18s ease}.card[data-coming-soon]:hover{transform:translateY(-2px);border-color:#9fc5c0}.page-topbar .page-kicker{display:none}.admin-dashboard-content{max-width:1500px}.dashboard-welcome{position:relative;overflow:hidden;min-height:90px;padding:18px 20px;border-radius:13px;background:radial-gradient(ellipse at 76% 130%,rgba(178,210,239,.5) 0 18%,transparent 19%),radial-gradient(ellipse at 54% 5%,rgba(211,226,246,.65) 0 25%,transparent 26%),linear-gradient(110deg,#e4effd,#f0f6ff 55%,#f8f4fa);margin-bottom:14px}.botanical-accent{position:absolute;z-index:0;right:0;bottom:-18px;width:150px;height:auto;opacity:.48;pointer-events:none;user-select:none}.dashboard-welcome h2,.dashboard-welcome p{position:relative;z-index:1}.dashboard-welcome h2{margin:0 0 4px;color:#071d4a;font-size:1.75rem;letter-spacing:-.035em}.dashboard-welcome p{margin:0;color:#466a9d;font-size:.85rem}.dashboard-card{position:relative;overflow:hidden}.dashboard-card::before{content:"";position:absolute;top:0;left:0;width:5px;height:100%;background:#8bb7e8}.dashboard-card:nth-child(2)::before,.dashboard-card:nth-child(6)::before{background:#c18be9}.dashboard-card:nth-child(3)::before,.dashboard-card:nth-child(7)::before{background:#e68ec5}.dashboard-card:nth-child(4)::before,.dashboard-card:nth-child(8)::before{background:#7bc8b0}        .dashboard-card .tile-icon{display:inline-flex;width:38px;height:38px;align-items:center;justify-content:center;border-radius:50%;color:#356caa;background:#eaf3ff}.dashboard-card .tile-icon svg{width:24px;height:24px;stroke-width:1.8}.dashboard-card:nth-child(2) .tile-icon,.dashboard-card:nth-child(6) .tile-icon{color:#8661a8;background:#f1eafe}.dashboard-card:nth-child(3) .tile-icon,.dashboard-card:nth-child(7) .tile-icon{color:#b06b91;background:#fbeaf3}.dashboard-card:nth-child(4) .tile-icon,.dashboard-card:nth-child(8) .tile-icon{color:#5d927d;background:#e8f3ed}.dashboard-lower{display:grid;grid-template-columns:1.05fr .95fr;gap:14px;margin-top:14px}.dashboard-lower .panel{min-height:166px}.dashboard-panel-title{display:flex;align-items:center;gap:9px;margin-bottom:4px;color:#071d4a;font:700 1.1rem Georgia,"Times New Roman",serif}.dashboard-panel-title svg{width:24px;height:24px;stroke-width:1.8;color:#356caa}.dashboard-panel-subtitle{margin:0 0 14px;color:#71829b;font-size:.75rem}.quick-actions{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.quick-action{display:flex;align-items:center;justify-content:flex-start;gap:10px;padding:14px;border-radius:9px;color:#173d73;text-decoration:none;font-size:.76rem;font-weight:700;background:#eaf3ff}.quick-action span{flex:1}.quick-action:nth-child(2){background:#e8f5ef;color:#17614f}.quick-action:nth-child(3){background:#f9eaf5;color:#7b2e6d}.quick-action:nth-child(4){background:#eeedff;color:#3e43a2}.quick-action svg{width:22px;height:22px;stroke-width:1.8;flex:0 0 auto}.quick-action svg:last-child{width:18px;height:18px}.quick-action:hover{transform:translateY(-1px);filter:brightness(.98)}.activity-list{list-style:none;padding:0;margin:0}.activity-list li{display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid #edf0f4;color:#1d3763;font-size:.76rem}.activity-list li:first-child{border-top:0}.activity-dot{width:9px;height:9px;border-radius:50%;background:#57a98d}.activity-list li:nth-child(2) .activity-dot{background:#cc6571}.activity-list li:nth-child(3) .activity-dot{background:#7e9bc8}.activity-list time{margin-left:auto;color:#7890b0;font-size:.68rem}@media(max-width:900px){.dashboard-lower{grid-template-columns:1fr}}@media(max-width:720px){.dashboard-welcome h2{font-size:1.7rem}.quick-actions{grid-template-columns:1fr}}
        .admin-generic-page{color:#102b43}.admin-generic-page h1,.admin-generic-page h2,.admin-generic-page h3{color:#071d4a;letter-spacing:-.025em}.admin-generic-page>h1{margin:0 0 16px;font-size:2rem}.admin-generic-page .page-intro{margin-bottom:16px}.admin-generic-page .page-intro h2{margin:0 0 3px;font-size:1.45rem}.admin-generic-page .page-intro p,.admin-generic-page .lede{color:#60728b}.admin-generic-page .panel{border-color:#e0e7f0;border-radius:13px;background:rgba(255,255,255,.94);box-shadow:0 10px 26px rgba(49,76,117,.08)}.admin-generic-page .panel-head{margin-bottom:16px}.admin-generic-page .panel-head h2{font-size:1.15rem}.admin-generic-page .button:not(.button-secondary):not(.button-danger),.admin-generic-page button.button-link{color:#fff;background:#2877be;box-shadow:0 4px 10px rgba(40,119,190,.16)}.admin-generic-page .button:not(.button-secondary):not(.button-danger):hover,.admin-generic-page button.button-link:hover{background:#2169aa}.admin-generic-page .button-secondary{color:#24476d;background:#fff;border-color:#d7e4f1}.admin-generic-page .button-secondary:hover{background:#edf5ff}.admin-generic-page .table-wrap,.admin-generic-page>section.panel>table{border-color:#e0e7f0;border-radius:13px;background:#fff;box-shadow:0 8px 22px rgba(49,76,117,.06)}.admin-generic-page table th{color:#49698c;background:#f5f8fc;border-bottom-color:#dfe8f2}.admin-generic-page table td{border-bottom-color:#e8eef5;color:#29445f}.admin-generic-page table tbody tr:hover{background:#f7fbff}.admin-generic-page .item-title{color:#173d63}.admin-generic-page .button-link{color:#155eab;font-weight:700;text-decoration:none}.admin-generic-page input,.admin-generic-page select,.admin-generic-page textarea{border-color:#d2dfeb;border-radius:9px}.admin-generic-page input:focus,.admin-generic-page select:focus,.admin-generic-page textarea:focus{border-color:#2877be;box-shadow:0 0 0 3px rgba(40,119,190,.12)}.admin-generic-page .status,.admin-generic-page .alert.success{border-color:#b9d8f2;color:#285783;background:#eaf4ff}.admin-generic-page .badge.active{color:#14846b;background:#e2f5ef}.admin-generic-page .form-panel{box-shadow:0 10px 26px rgba(49,76,117,.08)}@media(max-width:720px){.admin-generic-page>h1{font-size:1.7rem}.admin-generic-page .page-intro{flex-direction:column}}

        /* ---- layout rhythm ---- */
        .stack{display:flex;flex-direction:column;gap:16px}.stack-sm{display:flex;flex-direction:column;gap:10px}
        .stack .field-row{margin-bottom:0}
        .backlink{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-size:.88rem;text-decoration:none;font-weight:600}.backlink:hover{color:var(--accent)}
        .lede{color:var(--muted);max-width:60ch;margin:0}
        .split{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
        .panel h2{font-size:1.2rem;margin:0 0 2px}.panel h3{font-size:1rem;margin:0 0 2px;color:#1a3f43}
        .panel-head{margin-bottom:14px}
        .divider{border:0;border-top:1px solid var(--line);margin:16px 0}

        /* ---- status ---- */
        .status-dot{display:inline-block;width:9px;height:9px;border-radius:50%;flex:0 0 auto}
        .dot-live{background:#1f9d57;box-shadow:0 0 0 3px rgba(31,157,87,.18)}.dot-draft{background:#c98a1e}.dot-archived{background:#8aa0a2}
        .state-line{display:inline-flex;align-items:center;gap:8px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;font-size:.78rem;color:#1a3f43}

        /* ---- metric tiles ---- */
        .meta-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:10px}
        .tile{padding:12px 14px;border:1px solid var(--line);border-radius:16px;background:#fbfbf7}
        .tile .k{display:block;color:var(--muted);font-size:.7rem;letter-spacing:.07em;text-transform:uppercase}
        .tile .v{display:block;margin-top:5px;font-size:1.3rem;font-family:Georgia,serif;color:#062f34;line-height:1.1}
        .tile .v.sm{font-size:.95rem;font-family:inherit;font-weight:650}

        /* ---- health check ---- */
        .health-list{list-style:none;padding:0;margin:0;display:grid;gap:6px}
        .health-list li{display:flex;gap:9px;align-items:flex-start;font-size:.9rem}
        .health-list .mark{flex:0 0 auto;font-weight:800}.health-list .ok .mark{color:#1f9d57}.health-list .bad .mark{color:var(--danger)}
        .ready{font-weight:700;color:#1f9d57;margin:0}

        /* ---- dropdown menu ---- */
        .more{position:relative;display:inline-block}.more>summary{list-style:none;cursor:pointer}.more>summary::-webkit-details-marker{display:none}
        .more-menu{position:absolute;right:0;top:calc(100% + 6px);z-index:20;min-width:200px;padding:6px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 18px 40px rgba(25,64,59,.16)}
        .more-menu a,.more-menu button{display:block;width:100%;text-align:left;padding:9px 12px;border:0;border-radius:10px;background:transparent;color:var(--ink);text-decoration:none;font:inherit;cursor:pointer;white-space:nowrap}
        .more-menu a:hover,.more-menu button:hover{background:#f1f5f2}.more-menu .danger{color:var(--danger)}.more-menu hr{border:0;border-top:1px solid var(--line);margin:5px 0}

        /* ---- version history rows ---- */
        .version-row{display:flex;align-items:center;gap:14px;padding:14px 0;border-top:1px solid var(--line)}.version-row:first-of-type{border-top:0}.version-row .grow{flex:1;min-width:0}
        .version-row .meta{color:var(--muted);font-size:.84rem;margin-top:3px}
        .questionnaire-tabs{display:flex;flex-wrap:nowrap;gap:5px;padding:4px;overflow-x:auto;border:1px solid #dce5ee;border-radius:15px;background:rgba(255,255,255,.72)}
        .questionnaire-tabs .button{flex:1 1 0;justify-content:center;min-height:36px;padding:.5rem .62rem;border-radius:11px;font-size:.7rem;white-space:nowrap;box-shadow:none}
        .questionnaire-tabs .button svg{width:15px;height:15px;stroke-width:1.8}
        .questionnaire-workspace-grid{display:grid;grid-template-columns:minmax(0,1fr) 250px;gap:14px;align-items:start}.questionnaire-main-column{display:flex;flex-direction:column;gap:14px;min-width:0}
        .questionnaire-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.questionnaire-summary-card{display:grid;grid-template-columns:40px 1fr;column-gap:11px;align-items:center;min-height:100px;padding:14px;color:inherit;text-decoration:none}
        .questionnaire-bank-card:hover{transform:translateY(-2px);border-color:#a8d7d1}
        .questionnaire-summary-card .summary-icon{grid-row:span 3;display:grid;place-items:center;width:38px;height:38px;border-radius:50%;color:#356caa;background:#eaf3ff}.questionnaire-summary-card .summary-icon svg{width:22px;height:22px;stroke-width:1.8}
        .questionnaire-summary-card .summary-icon.sage{color:#5d927d;background:#e8f3ed}.questionnaire-summary-card .summary-icon.mauve{color:#8661a8;background:#f1eafe}.questionnaire-summary-card .summary-icon.blush{color:#596b78;background:#eef1f3}.questionnaire-summary-card .summary-icon.teal{color:#388c88;background:#dff2ef}
        .questionnaire-summary-card .k{color:#617276;font-size:.7rem;letter-spacing:.06em;text-transform:uppercase}.questionnaire-summary-card strong{color:#071d4a;font:700 1.7rem Georgia,"Times New Roman",serif;line-height:1}.questionnaire-summary-card .muted{font-size:.72rem}
        .questionnaire-overview-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(260px,.75fr);gap:14px;align-items:start}
        .questionnaire-list-tools{display:flex;align-items:center;gap:8px}.questionnaire-search{display:flex;align-items:center;gap:7px;width:190px;height:38px;padding:0 10px;border:1px solid #dce5ee;border-radius:10px;color:#69809e;background:#fff}.questionnaire-search svg{width:17px;height:17px;flex:0 0 auto}.questionnaire-search input{min-width:0;padding:0;border:0;box-shadow:none;background:transparent;font-size:.72rem}.questionnaire-search input:focus{box-shadow:none}.questionnaire-filter{min-height:38px!important;padding:.5rem .75rem!important;border-radius:10px!important;font-size:.72rem!important}.questionnaire-filter svg{width:16px;height:16px}.questionnaire-filter.is-selected{color:#fff;background:#356caa;border-color:#356caa}
        .questionnaire-table{overflow-x:auto}.questionnaire-table-head,.questionnaire-row{display:grid;grid-template-columns:58px minmax(150px,1.25fr) minmax(150px,1.1fr) 85px 110px minmax(180px,1.45fr);gap:12px;align-items:center;min-width:770px}.questionnaire-table-head{padding:10px 12px;border-radius:8px;color:#496368;background:#f0f5fa;font-size:.63rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase}.questionnaire-row{padding:14px 12px;border-bottom:1px solid #e7ece9}.questionnaire-row:last-child{border-bottom:0}.questionnaire-row[hidden]{display:none}.questionnaire-title-cell{display:flex;flex-direction:column;gap:3px;min-width:0}.questionnaire-title-cell .item-title{overflow-wrap:anywhere}.questionnaire-title-cell small,.questionnaire-updated small{color:var(--muted);font-size:.65rem}.questionnaire-summary-cell{display:grid;gap:3px;color:#526b75;font-size:.7rem}.questionnaire-summary-cell span{display:flex;align-items:center;gap:5px}.questionnaire-summary-cell svg{width:13px;height:13px;color:#5e86b5}.questionnaire-updated{display:flex;flex-direction:column;gap:3px;color:#274c65;font-size:.72rem}.questionnaire-row-actions{justify-content:flex-end;gap:5px}.questionnaire-row-actions .button{min-height:34px;padding:.48rem .6rem;font-size:.69rem}.questionnaire-row-actions .button svg{width:15px;height:15px}.questionnaire-row-actions .questionnaire-preview-action{display:inline-flex}.questionnaire-filter-empty{padding:22px;text-align:center}
        .questionnaire-helper{position:sticky;top:18px}.questionnaire-helper .panel h2{display:flex;align-items:center;gap:8px;margin-bottom:12px}.questionnaire-helper .panel h2 svg{width:19px;height:19px;color:#356caa;stroke-width:1.9}.questionnaire-quick-actions{grid-template-columns:1fr;margin-top:0}.questionnaire-quick-actions .quick-action{padding:10px 12px}.questionnaire-quick-actions .quick-action.primary{color:#fff;background:#267abd}.questionnaire-quick-actions .quick-action.primary:hover{background:#1d68a7}.workflow-card ol{display:grid;gap:12px;margin:15px 0 0;padding:0;list-style:none}.workflow-card li{position:relative;display:flex;align-items:flex-start;gap:10px;color:#335475;font-size:.78rem}.workflow-card li:not(:last-child)::after{content:"";position:absolute;left:12px;top:27px;width:1px;height:22px;background:#b8d1ee}.workflow-card li span{position:relative;z-index:1;display:grid;place-items:center;width:25px;height:25px;border-radius:50%;color:#356caa;background:#dceaff;font-size:.72rem;font-weight:700}.workflow-card li div{display:grid;gap:2px;padding-top:1px}.workflow-card a{color:inherit;font-weight:700;text-decoration:none}.workflow-card a:hover{color:#147b78}.workflow-card small{color:#71829b;font-size:.68rem;line-height:1.25}

        /* ---- repeatable editor rows (options, result ranges) ---- */
        .editor-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;padding:14px;border:1px solid var(--line);border-radius:16px;background:#fbfbf7}
        .editor-row + .editor-row{margin-top:10px}
        .editor-row .fld{flex:1 1 130px;min-width:0}.editor-row .fld.grow{flex:2 1 200px}.editor-row .fld.narrow{flex:0 1 92px}
        .editor-row label{margin-bottom:5px}
        .editor-row .remember{margin:0 4px 8px 0}
        .editor-row .row-remove{flex:0 0 auto}
        .add-row{margin-top:12px}

        /* ---- move up / down ---- */
        .reorder{display:flex;flex-direction:column;gap:2px;flex:0 0 auto}
        .reorder form{margin:0}
        .arrow{width:26px;height:22px;padding:0;border:1px solid var(--line);border-radius:7px;background:#fff;color:#496368;font-size:.6rem;line-height:1;cursor:pointer}
        .arrow:hover:not(:disabled){background:#f1f5f2;color:var(--ink)}
        .arrow:disabled{opacity:.3;cursor:default}
        .reorder-cell{padding-right:0}
        .quick-add textarea{min-height:0}

        /* ---- questionnaire builder: section → questions → answers ---- */
        .eyebrow{color:#496368;font-size:.72rem;letter-spacing:.09em;text-transform:uppercase;font-weight:700}
        .section-panel{border-left:4px solid var(--accent)}
        .section-head{display:flex;gap:12px;align-items:flex-start;min-width:0;flex:1}
        .questions-label{margin:18px 0 10px;padding-top:14px;border-top:1px solid var(--line);font-weight:700;color:#1a3f43}
        .question-list{display:grid;gap:10px}
        .question-card{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border:1px solid var(--line);border-radius:16px;background:#fbfbf7}
        .question-body{flex:1;min-width:0}
        .question-text{overflow-wrap:anywhere;line-height:1.35}
        .question-meta{margin-top:3px;color:var(--muted);font-size:.82rem}
        .answer-list{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-wrap:wrap;gap:6px 14px;font-size:.86rem;color:#24474b}
        .answer-list li{display:inline-flex;align-items:center;gap:6px}
        .answer-dot{width:8px;height:8px;border-radius:50%;border:1.5px solid #82b8b4;flex:0 0 auto}
        .question-actions{display:flex;gap:8px;flex:0 0 auto}
        .question-actions form{margin:0}
        .bulk-add{margin-top:14px;padding:14px;border:1px dashed #b8dcd4;border-radius:16px;background:#f7faf8}
        .bulk-add textarea{min-height:0}
        .review-list{gap:9px;font-size:.98rem}.review-list .mark{width:18px;text-align:center}
        .attention-list{list-style:none;padding:0;margin:0;display:grid;gap:10px;counter-reset:item}
        .attention-list li{display:flex;gap:14px;align-items:center;padding:14px 16px;border:1px solid var(--line);border-radius:16px;background:#fff;counter-increment:item}
        .attention-list li::before{content:counter(item) ".";flex:0 0 auto;font-weight:800;color:#496368}
        .attention-list .grow{flex:1;min-width:0}
        .button:disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
        .empty-state{text-align:center;padding:36px 24px}
        .empty-state.small{padding:22px 16px;border:1px dashed var(--line);border-radius:16px;background:#fbfbf7}
        @media(max-width:640px){.question-card{flex-wrap:wrap}.question-actions{width:100%;justify-content:flex-end}.section-head{width:100%}}

        /* ---- advanced settings disclosure ---- */
        .advanced{border:1px solid var(--line);border-radius:16px;padding:14px 18px;background:#fbfbf7}
        .advanced>summary{cursor:pointer;font-weight:650;color:#1a3f43;list-style:none}.advanced>summary::-webkit-details-marker{display:none}
        .advanced>summary::before{content:"▸ ";color:var(--muted)}.advanced[open]>summary::before{content:"▾ "}

        /* ---- guided setup stepper ---- */
        .wizard-steps{list-style:none;display:flex;flex-wrap:wrap;gap:8px;padding:0;margin:0}
        .wizard-steps li{display:flex;align-items:center;gap:9px;padding:9px 15px;border-radius:999px;background:#f1f5f2;color:var(--muted);font-size:.86rem;font-weight:650}
        .wizard-steps li .n{display:grid;place-items:center;width:20px;height:20px;border-radius:50%;background:#dbe4e0;color:#3d5551;font-size:.74rem}
        .wizard-steps li.current{background:rgba(20,123,120,.12);color:var(--teal)}
        .wizard-steps li.current .n{background:var(--accent);color:#fff}
        .wizard-steps li.done{color:#1f9d57}
        .wizard-steps li.done .n{background:#dff1e7;color:#1f9d57}
        .wizard-steps li.attention{color:#9b3d2e;background:#fbebe7}
        .wizard-steps li.attention .n{background:#e9b3a8;color:#7a2a1d}
        .wizard-steps li a{display:inline-flex;align-items:center;gap:9px;color:inherit;text-decoration:none}
        .wizard-steps li:has(a):hover{filter:brightness(.96)}
        .wizard-hint{font-weight:500;opacity:.85}
        .publish-panel{border-color:#b8dcd4;background:#f2f9f6}

        /* ---- read-only preview ---- */
        .preview-shell{width:min(640px,100%);margin:0 auto;display:flex;flex-direction:column;gap:16px}
        .preview-progress{height:8px;border-radius:8px;background:#e7ece9}.preview-progress>span{display:block;height:8px;border-radius:8px;background:var(--accent)}
        .preview-q{padding:16px 0;border-top:1px solid var(--line)}.preview-q:first-of-type{border-top:0;padding-top:0}
        .preview-opt{display:flex;gap:10px;align-items:center;padding:10px 13px;margin-top:7px;border:1px solid var(--line);border-radius:12px;color:var(--muted)}

        @media(max-width:1050px){.questionnaire-workspace-grid{grid-template-columns:1fr}.questionnaire-helper{position:static;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.questionnaire-summary{grid-template-columns:repeat(5,minmax(0,1fr))}}@media(max-width:860px){.questionnaire-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.split{flex-direction:column}.version-row{flex-wrap:wrap}.questionnaire-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.questionnaire-helper{display:grid;grid-template-columns:1fr}.questionnaire-tabs{scrollbar-width:none}.questionnaire-tabs::-webkit-scrollbar{display:none}.questionnaire-tabs .button{flex:0 0 auto}.questionnaire-row-actions{padding-left:0!important}}@media(max-width:520px){.questionnaire-summary{grid-template-columns:1fr}}

        /* Administrator sign-in: intentionally scoped so authenticated pages retain their current layout. */
        .admin-login-shell{display:grid;min-height:100vh;place-items:center;padding:36px;background:radial-gradient(circle at 12% 12%,rgba(255,255,255,.28),transparent 26%),radial-gradient(circle at 88% 85%,rgba(255,222,239,.42),transparent 29%),linear-gradient(120deg,#8c48e8 0%,#7442dc 45%,#d395af 100%)}
        .admin-login-card{display:grid;grid-template-columns:minmax(340px,.82fr) minmax(450px,1.18fr);width:min(1120px,100%);min-height:650px;overflow:hidden;border:1px solid rgba(255,255,255,.78);border-radius:28px;background:#fff;box-shadow:0 28px 72px rgba(52,20,100,.28)}
        .admin-login-form-pane{display:grid;align-items:center;padding:52px clamp(34px,6vw,84px);background:rgba(255,255,255,.96)}
        .admin-login-content{width:min(100%,370px);margin:0 auto}
        .admin-login-mark{display:grid;width:48px;height:48px;place-items:center;margin:0 auto 24px;border-radius:10px;color:#fff;background:linear-gradient(135deg,#9b5cff,#6932d9);box-shadow:0 10px 20px rgba(109,51,217,.25);font-size:1.85rem;font-weight:800;line-height:1}
        .admin-login-brand{margin:0 0 8px;text-align:center;color:#6f37d8;font-size:.82rem;font-weight:750;letter-spacing:.04em;text-transform:uppercase}
        .admin-login-card h1{margin:0;text-align:center;color:#21153d;font-family:"Segoe UI",Inter,system-ui,sans-serif;font-size:clamp(1.7rem,2.8vw,2.15rem);font-weight:800;letter-spacing:-.045em}
        .admin-login-subtitle{margin:10px 0 32px;text-align:center;color:#766f85;font-size:.92rem}
        .admin-login-form label:not(.admin-login-remember){display:block;margin:0 0 7px;color:#392e52;font-size:.78rem;font-weight:700}
        .admin-login-form input:not([type="checkbox"]){width:100%;min-height:48px;margin:0 0 18px;padding:0 14px;border:1px solid #e1ddea;border-radius:8px;color:#2d2443;background:#fbfaff;box-shadow:none;transition:border-color .18s,box-shadow .18s}
        .admin-login-form input:not([type="checkbox"]):focus{outline:0;border-color:#8250e4;box-shadow:0 0 0 4px rgba(130,80,228,.13);background:#fff}
        .admin-login-form .error{margin:-10px 0 14px;color:#c43f62;font-size:.8rem}
        .admin-login-remember{display:flex;align-items:center;gap:8px;margin:1px 0 24px;color:#655c73;font-size:.8rem;cursor:pointer}
        .admin-login-remember input{width:15px;height:15px;margin:0;accent-color:#7442dc}
        .admin-login-submit{width:100%;min-height:48px;border-radius:8px;background:linear-gradient(100deg,#456c8d,#60a1b1);box-shadow:0 10px 19px rgba(66, 136, 202, 0.25);font-size:.9rem;transition:transform .18s,box-shadow .18s,filter .18s}
        .admin-login-submit:hover{filter:brightness(1.05);transform:translateY(-1px);box-shadow:0 13px 23px rgba(91,117,141,.3)}
        .admin-login-submit:focus-visible{outline:3px solid rgba(111,135,159,.35);outline-offset:3px}
        .admin-login-visual-pane{position:relative;display:grid;min-width:0;place-items:center;overflow:hidden;padding:46px;background:linear-gradient(145deg,#f9f7ff 0%,#f2effb 58%,#eae4f6 100%)}
        .admin-login-illustration{position:relative;z-index:1;display:block;width:min(100%,620px);height:auto;filter:drop-shadow(0 24px 18px rgba(76,46,123,.16))}
        .admin-login-orb{position:absolute;border-radius:50%;background:rgba(154,92,255,.13);filter:blur(1px)}
        .admin-login-orb-one{width:240px;height:240px;top:-105px;right:-74px}.admin-login-orb-two{width:170px;height:170px;bottom:-72px;left:-58px;background:rgba(231,142,185,.18)}
        @media(max-width:840px){.admin-login-shell{padding:22px}.admin-login-card{grid-template-columns:1fr;max-width:620px}.admin-login-form-pane{min-height:590px}.admin-login-visual-pane{min-height:300px;padding:20px}.admin-login-illustration{width:min(100%,430px)}}
        @media(max-width:420px){.admin-login-shell{padding:0}.admin-login-card{min-height:100vh;border:0;border-radius:0}.admin-login-form-pane{padding:42px 26px;min-height:0}.admin-login-visual-pane{display:none}}

        /* Floral SheZen login form treatment. */
        .admin-login-content{width:min(100%,410px);text-align:center}.admin-login-floral-logo{display:block;width:min(255px,72%);height:auto;margin:0 auto 4px}.admin-login-tagline{margin:0 0 28px;color:#9c689a;font-size:.64rem;font-weight:800;letter-spacing:.17em;text-transform:uppercase}.admin-login-tagline span{padding:0 2px;color:#c95a9f}
        .admin-login-card h1{color:#40234f;font-size:clamp(1.75rem,2.8vw,2.28rem);line-height:1.1}.admin-login-subtitle{margin:10px 0 26px;color:#6d738d;font-size:.89rem;line-height:1.45}
        .admin-login-form{text-align:left}.admin-login-form label:not(.admin-login-remember){margin-bottom:6px;color:#4b3157;font-size:.75rem}.admin-login-input-wrap{position:relative;margin:0 0 16px}.admin-login-form input:not([type="checkbox"]){min-height:45px;margin:0;padding:0 43px;border-color:#ded1e7;border-radius:7px;color:#4d3659;background:#fff;font-size:.83rem}.admin-login-form input:not([type="checkbox"])::placeholder{color:#aaa0ba}.admin-login-input-wrap:focus-within .admin-login-field-icon{color:#8c4889}.admin-login-field-icon,.admin-login-password-icon{position:absolute;top:50%;z-index:1;width:19px;height:19px;color:#9d82a7;transform:translateY(-50%);pointer-events:none}.admin-login-field-icon{left:13px}.admin-login-password-icon{right:13px}.admin-login-field-icon svg,.admin-login-password-icon svg{display:block;width:100%;height:100%}
        .admin-login-options{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:2px 0 20px}.admin-login-remember{margin:0;font-size:.74rem}.admin-login-forgot{color:#bd5f9e;font-size:.74rem;white-space:nowrap}.admin-login-submit{min-height:45px;border-radius:7px}.admin-login-submit:hover{background:linear-gradient(100deg,#829db5,#7890a7)}.admin-login-footer{margin-top:26px;padding-top:10px;border-top:1px solid #dfd6e4;color:#8a7897;font-size:.72rem;text-align:center}.admin-login-footer span{padding:0 5px;color:#d1c2d7}.admin-login-footer em{font-style:italic}
        @media(max-width:840px){.admin-login-floral-logo{width:min(235px,70%)}.admin-login-tagline{margin-bottom:24px}}@media(max-width:420px){.admin-login-options{align-items:flex-start;flex-direction:column;gap:8px}.admin-login-forgot{align-self:flex-end}}

        /* Final wireframe proportions for the public administrator sign-in page. */
        .admin-login-shell{min-height:100svh;padding:clamp(20px,3.5vh,40px) clamp(18px,3vw,32px);background:radial-gradient(ellipse 38% 33% at 51% 8%,rgba(91,153,222,.7),transparent 72%),radial-gradient(ellipse 34% 29% at 21% 58%,rgba(157,214,248,.72),transparent 74%),radial-gradient(ellipse 36% 32% at 83% 39%,rgba(202,231,249,.78),transparent 76%),#fff}
        .admin-login-card{grid-template-columns:1fr 1fr;width:min(1000px,100%);min-height:0;height:min(620px,calc(100svh - 64px));border-radius:32px;border:1px solid rgba(255,255,255,.9);box-shadow:0 26px 64px rgba(160,76,160,.28)}
        .admin-login-form-pane{padding:clamp(28px,4vh,42px) clamp(32px,4.5vw,60px);background:linear-gradient(145deg,#fff 0%,#fffafd 100%)}.admin-login-content{width:min(100%,430px)}
        .admin-login-floral-logo{width:135px;max-width:54%;height:135px;margin:0 auto 16px;object-fit:contain;filter:saturate(.62) contrast(.94);opacity:.9}.admin-login-card h1{font-size:clamp(1.85rem,2.6vw,2.35rem);letter-spacing:-.055em}.admin-login-subtitle{margin:10px auto 20px;max-width:370px;font-size:.94rem;line-height:1.38}
        .admin-login-form input:not([type="checkbox"]){min-height:54px;border-color:#d8c4df;border-radius:9px;font-size:.92rem}.admin-login-input-wrap{margin-bottom:15px}.admin-login-form label:not(.admin-login-remember){margin-bottom:7px;font-size:.82rem}.admin-login-field-icon,.admin-login-password-icon{width:20px;height:20px}.admin-login-field-icon{left:17px}.admin-login-password-icon{right:17px}.admin-login-form input:not([type="checkbox"]){padding-left:52px;padding-right:52px}
        .admin-login-password-icon{right:8px;display:grid;width:40px;height:40px;place-items:center;padding:0;border:0;border-radius:8px;background:transparent;cursor:pointer;pointer-events:auto}.admin-login-password-icon svg{width:20px;height:20px}.admin-login-password-icon:hover{color:#884887}.admin-login-password-icon:focus-visible{outline:2px solid #884887;outline-offset:2px}.admin-login-password-icon[aria-pressed="true"] .admin-login-eye-slash{display:none}
        .admin-login-options{justify-content:flex-start;margin:3px 0 15px}.admin-login-remember{font-size:.87rem;font-weight:650}.admin-login-remember input{width:19px;height:19px;border-radius:4px}.admin-login-submit{min-height:54px;border-radius:10px;font-size:1rem}.admin-login-submit span{margin-left:9px;font-size:1.35rem;font-weight:400;line-height:0}.admin-login-footer{margin-top:20px;padding-top:13px;font-size:.78rem}
        .admin-login-visual-pane{padding:16px;background:radial-gradient(circle at 50% 50%,#fff 0%,#fff8fc 49%,#fde9f7 100%)}.admin-login-illustration{width:min(110%,560px);max-height:min(480px,66svh);object-fit:contain;filter:drop-shadow(0 22px 18px rgba(155,84,145,.14))}.admin-login-orb-one{width:240px;height:240px;top:-110px;right:-95px;background:rgba(215,137,201,.34)}.admin-login-orb-two{width:260px;height:260px;bottom:-135px;left:-125px;background:rgba(227,172,231,.3)}
        @media(max-width:840px){.admin-login-shell{padding:18px}.admin-login-card{grid-template-columns:1fr;max-width:580px;height:auto}.admin-login-form-pane{min-height:0}.admin-login-visual-pane{display:none}}@media(max-width:420px){.admin-login-shell{padding:0}.admin-login-card{border-radius:0}.admin-login-form-pane{padding:30px 24px}.admin-login-floral-logo{width:123px;height:123px}}
        @media(max-height:700px) and (min-width:841px){.admin-login-shell{padding:16px}.admin-login-card{height:min(620px,calc(100svh - 32px))}.admin-login-form-pane{padding:22px 48px}.admin-login-floral-logo{width:112px;height:112px;margin-bottom:10px}.admin-login-subtitle{margin-bottom:14px}.admin-login-form input:not([type="checkbox"]){min-height:48px}.admin-login-input-wrap{margin-bottom:10px}.admin-login-options{margin-bottom:10px}.admin-login-submit{min-height:48px}.admin-login-footer{margin-top:13px;padding-top:10px}.admin-login-illustration{max-height:420px}}
        .admin-login-visual-pane--office{padding:0;background:#f7f0f2}
        .admin-login-visual-pane--office .admin-login-illustration{position:absolute;inset:0;width:100%;height:100%;max-height:none;object-fit:cover;filter:none}
    </style>
    <style>.topbar-page-title{display:none!important}</style>
    <style>
        .coming-soon-modal{position:fixed;inset:0;z-index:200;display:grid;place-items:center;padding:20px;background:rgba(12,39,64,.42);backdrop-filter:blur(3px)}
        .coming-soon-modal[hidden]{display:none}.coming-soon-card{position:relative;width:min(410px,100%);padding:28px 28px 24px;border:1px solid #d8e5f2;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(17,52,84,.25);text-align:center}.coming-soon-close{position:absolute;top:11px;right:14px;width:32px;height:32px;border:0;border-radius:50%;background:transparent;color:#65809b;font-size:1.45rem;line-height:1;cursor:pointer}.coming-soon-close:hover{background:#edf4fb;color:#193f63}.coming-soon-icon{display:grid;place-items:center;width:54px;height:54px;margin:0 auto 14px;border-radius:50%;color:#2877be;background:#e8f3ff}.coming-soon-icon svg{width:27px;height:27px}.coming-soon-card h2{margin:0 0 7px;color:#123653;font-size:1.3rem}.coming-soon-card p{margin:0;color:#60778e;font-size:.9rem;line-height:1.45}.coming-soon-ok{min-width:100px;margin-top:20px;border:0;border-radius:9px;padding:10px 20px;color:#fff;background:#2877be;font-weight:700;cursor:pointer}.coming-soon-ok:hover{background:#1d68a7}
    </style>
</head>
<body class="{{ request()->routeIs('admin.questionnaires.index') ? 'questionnaire-index-page' : (request()->routeIs('admin.dashboard') || request()->routeIs('admin.questionnaires.*') || request()->routeIs('admin.questions.*') ? '' : 'admin-generic-page') }}">
@auth
<div class="admin-shell">
    <aside class="sidebar">
        <div class="brand-block"><div class="brand-title">SheZen<br>Harmony</div><div class="brand-subtitle">Administration</div></div>
        <nav aria-label="Administration">
            <section class="nav-section"><div class="nav-label">Overview</div><a class="side-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="side-icon"><i data-lucide="layout-dashboard"></i></span>Dashboard</a></section>
            <section class="nav-section"><div class="nav-label">Users</div><a class="side-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}"><span class="side-icon"><i data-lucide="users"></i></span>Registered Students</a></section>
            <section class="nav-section"><div class="nav-label">Resource</div><a class="side-link {{ request()->routeIs('admin.resources.*') ? 'active' : '' }}" href="{{ route('admin.resources.index') }}"><span class="side-icon"><i data-lucide="phone"></i></span>Helpline Resources</a></section>
            <section class="nav-section"><div class="nav-label">Assessment</div><a class="side-link {{ (request()->routeIs('admin.questionnaires.*') || request()->routeIs('admin.questions.*')) ? 'active' : '' }}" href="{{ route('admin.questionnaires.index') }}"><span class="side-icon"><i data-lucide="clipboard-list"></i></span>Questionnaire Management</a><a class="side-link {{ request()->routeIs('admin.student-stress.*') ? 'active' : '' }}" href="{{ route('admin.student-stress.index') }}"><span class="side-icon"><i data-lucide="bar-chart-3"></i></span>Stress Level Assessment</a></section>
            <section class="nav-section"><div class="nav-label">Wellbeing Activities</div><a class="side-link {{ request()->routeIs('admin.interventions.*') ? 'active' : '' }}" href="{{ route('admin.interventions.index') }}"><span class="side-icon"><i data-lucide="leaf"></i></span>Support Content</a><a class="side-link {{ request()->routeIs('admin.wellbeing_activities.*') ? 'active' : '' }}" href="{{ route('admin.wellbeing_activities.index') }}"><span class="side-icon"><i data-lucide="play-circle"></i></span>Video Activities</a></section>
            <section class="nav-section"><div class="nav-label">Personal Guidance</div><a class="side-link {{ request()->routeIs('admin.personal-guidance.*') ? 'active' : '' }}" href="{{ route('admin.personal-guidance.index') }}"><span class="side-icon"><i data-lucide="user-round"></i></span>Personal Guidance</a></section>
            <section class="nav-section"><div class="nav-label">Positive Engagement</div><a class="side-link {{ request()->routeIs('admin.positive-engagement.*') && ! request()->routeIs('admin.positive-engagement.games.*') && ! request()->routeIs('admin.positive-engagement.games-quizzes.*') ? 'active' : '' }}" href="{{ route('admin.positive-engagement.index') }}"><span class="side-icon"><i data-lucide="gem"></i></span>Motivational Content</a><a class="side-link pending {{ request()->routeIs('admin.positive-engagement.games.*') ? 'active' : '' }}" href="{{ route('admin.positive-engagement.games.index') }}" data-coming-soon="Games"><span class="side-icon"><i data-lucide="gamepad-2"></i></span>Games</a><a class="side-link {{ request()->routeIs('admin.positive-engagement.games-quizzes.*') ? 'active' : '' }}" href="{{ route('admin.positive-engagement.games-quizzes.index') }}"><span class="side-icon"><i data-lucide="list-checks"></i></span>Quizzes</a></section>
            <section class="nav-section"><div class="nav-label">Shezen Chat Buddy</div><a class="side-link pending {{ request()->routeIs('admin.chatbuddy.*') ? 'active' : '' }}" href="{{ route('admin.chatbuddy.index') }}" data-coming-soon="Shezen Chat Buddy"><span class="side-icon"><i data-lucide="message-circle"></i></span>Shezen Chat Buddy</a></section>
        </nav>
    </aside>
    <div class="main-column"><header class="page-topbar"><div class="topbar-search" aria-label="Search"><i data-lucide="search"></i><span>Search...</span></div>@if (! request()->routeIs('admin.dashboard') && ! request()->routeIs('admin.questionnaires.*') && ! request()->routeIs('admin.questions.*'))<div class="topbar-page-title"><h1>@yield('page-title', trim($__env->yieldContent('title')))</h1><p class="page-kicker">Logged in as {{ auth()->user()->name }} · SheZen Harmony administration</p></div>@endif<div class="topbar-actions"><i data-lucide="bell" aria-label="Notifications" title="Notifications" style="width:20px;height:20px;color:#355f9a;stroke-width:1.8"></i><div class="topbar-profile"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span>{{ auth()->user()->name }}</span><i class="profile-chevron" data-lucide="chevron-down"></i></div><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="topbar-signout" type="submit"><i data-lucide="log-out"></i>Sign out</button></form></div></header>@yield('body')</div>
</div>
@else
@yield('body')
@endauth
<div class="coming-soon-modal" data-coming-soon-modal hidden>
    <section class="coming-soon-card" role="dialog" aria-modal="true" aria-labelledby="coming-soon-title">
        <button class="coming-soon-close" type="button" data-coming-soon-close aria-label="Close">&times;</button>
        <div class="coming-soon-icon"><i data-lucide="sparkles"></i></div>
        <h2 id="coming-soon-title">Coming soon</h2>
        <p><span data-coming-soon-name>This area</span> is planned for a future development release.</p>
        <button class="coming-soon-ok" type="button" data-coming-soon-close>Got it</button>
    </section>
</div>
<script src="https://unpkg.com/lucide@0.468.0" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
    }
    var comingSoonModal = document.querySelector('[data-coming-soon-modal]');
    var comingSoonName = comingSoonModal?.querySelector('[data-coming-soon-name]');
    function closeComingSoon() { if (comingSoonModal) comingSoonModal.hidden = true; }
    comingSoonModal?.querySelectorAll('[data-coming-soon-close]').forEach(function (button) { button.addEventListener('click', closeComingSoon); });
    comingSoonModal?.addEventListener('click', function (event) { if (event.target === comingSoonModal) closeComingSoon(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeComingSoon(); });
    document.querySelectorAll('[data-coming-soon]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            if (comingSoonName) comingSoonName.textContent = link.dataset.comingSoon;
            if (comingSoonModal) comingSoonModal.hidden = false;
        });
    });
});
</script>
</body>
</html>
