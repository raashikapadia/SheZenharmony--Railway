<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Administration') &middot; SheZen Harmony</title>
    <style>
        :root{color-scheme:light;--ink:#102f34;--muted:#617276;--teal:#103f41;--teal2:#205b5c;--accent:#147b78;--canvas:#f8f8f3;--surface:#fff;--line:#d7dfdc;--danger:#c63d43;font-family:"Segoe UI",Inter,system-ui,sans-serif;color:var(--ink);background:var(--canvas)}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--canvas)}a{color:inherit}h1,h2,h3,.brand-title,.metric{font-family:Georgia,"Times New Roman",serif}h1,h2,h3,p{margin-top:0}button,input,select,textarea{font:inherit}
        .admin-shell{min-height:100vh;display:grid;grid-template-columns:280px minmax(0,1fr)}.sidebar{position:sticky;top:0;height:100vh;overflow-y:auto;padding:30px 18px;color:#eef8f6;background:var(--teal)}.brand-block{padding:0 6px 30px}.brand-title{font-size:1.55rem;font-weight:700;letter-spacing:-.02em}.brand-subtitle{margin-top:3px;color:#b9d6d2;font-size:.92rem}.nav-section{margin:0 0 26px}.nav-label{padding:0 10px 8px;color:#82b8b4;font-size:.72rem;letter-spacing:.09em;text-transform:uppercase}.side-link{display:flex;align-items:center;gap:12px;min-height:44px;margin:3px 0;padding:10px 12px;border-radius:24px;color:#eff8f7;text-decoration:none;transition:.18s}.side-link:hover{background:rgba(255,255,255,.08);transform:translateX(2px)}.side-link.active{background:var(--teal2)}.side-icon{width:20px;text-align:center;color:#b9d6d2;font-size:1.05rem}.side-signout{width:100%;border:0;cursor:pointer;background:transparent;text-align:left}
        .main-column{min-width:0}.page-topbar{min-height:98px;display:flex;justify-content:space-between;gap:24px;align-items:center;padding:20px 30px;background:#fff;border-bottom:1px solid var(--line)}.page-topbar h1{margin:0;font-size:clamp(1.6rem,2.4vw,2rem);line-height:1.05;color:#082d32}.page-kicker{margin:5px 0 0;color:var(--muted);font-size:.9rem}.topbar-actions{display:flex;align-items:center;gap:12px}.content{width:min(1500px,calc(100% - 48px));margin:30px auto 50px}.page-intro{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:20px}.page-intro h2{margin-bottom:4px;font-size:1.55rem}.muted{color:var(--muted)}
        .cards{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr));gap:18px}.card,.panel{border:1px solid var(--line);background:var(--surface);box-shadow:0 16px 34px rgba(25,64,59,.07)}.card{min-height:128px;padding:22px;border-radius:30px}.metric{margin-top:10px;color:#062f34;font-size:2rem;line-height:1}.metric-note{margin-top:7px;color:var(--muted);font-size:.78rem}.panel{padding:24px;border-radius:28px}
        .button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:.7rem 1.1rem;border:0;border-radius:22px;color:#fff;background:var(--accent);box-shadow:0 3px 8px rgba(11,74,72,.18);font-weight:650;text-decoration:none;cursor:pointer}.button:hover{background:#188b86}.button-secondary{color:var(--ink);background:#fbfbf7;border:1px solid var(--line)}.button-secondary:hover{background:#f1f5f2}.button-danger{color:#fff;background:var(--danger)}.button-link{color:var(--accent);background:transparent;box-shadow:none}.actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center}
        .table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:24px;background:#fff}table{width:100%;border-collapse:collapse}th,td{padding:16px 18px;border-bottom:1px solid #e7ece9;text-align:left;vertical-align:middle}th{color:#496368;font-size:.76rem;letter-spacing:.05em;text-transform:uppercase}tbody tr:last-child td{border-bottom:0}tbody tr:hover{background:#fafcf9}.item-title{color:#062f34;font-weight:700}.badge{display:inline-flex;padding:5px 10px;border-radius:999px;color:#53666a;background:#edf1ef;font-size:.78rem;font-weight:650;text-transform:capitalize}.badge.active{color:#0c6865;background:#dff1ed}
        .form-panel{max-width:1100px}.form-section{padding:22px 0;border-top:1px solid var(--line)}.form-section:first-of-type{padding-top:0;border-top:0}label{display:block;margin:0 0 7px;color:#24474b;font-size:.85rem;font-weight:650}input[type=email],input[type=password],input[type=text],input[type=number],input[type=url],select,textarea{width:100%;padding:.78rem .9rem;border:1px solid #cfdad6;border-radius:13px;outline:none;background:#fff;color:var(--ink)}input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(20,123,120,.12)}textarea{min-height:7rem;resize:vertical}.field-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:16px}.remember{display:flex;align-items:center;gap:8px;margin:14px 0}.remember input{accent-color:var(--accent)}.status{padding:12px 16px;margin-bottom:18px;border:1px solid #b8dcd4;border-radius:14px;color:#125b58;background:#e6f4f0}.errors,.error{color:#9c2633}.errors{padding:14px 34px;border:1px solid #efc1c5;border-radius:14px;background:#fff1f2}.error{margin-top:5px;font-size:.86rem}
        .auth-wrap{display:grid;min-height:100vh;place-items:center;padding:24px;background:linear-gradient(135deg,var(--teal) 0 45%,var(--canvas) 45%)}.auth-card{width:min(440px,100%);padding:36px;border:1px solid var(--line);border-radius:28px;background:#fff;box-shadow:0 24px 60px rgba(3,47,48,.22)}.auth-card .brand{color:var(--accent);font-family:Georgia,serif;font-size:1.2rem;font-weight:700}.auth-card h1{margin:8px 0}
        @media(max-width:1000px){.admin-shell{grid-template-columns:220px minmax(0,1fr)}.cards{grid-template-columns:repeat(2,minmax(170px,1fr))}}@media(max-width:720px){.admin-shell{display:block}.sidebar{position:static;width:100%;height:auto;padding:20px}.brand-block{padding-bottom:14px}.sidebar nav{display:grid;grid-template-columns:repeat(2,1fr);gap:4px}.nav-section{display:contents}.nav-label{display:none}.page-topbar{min-height:auto;padding:18px 20px}.content{width:min(100% - 28px,1500px);margin-top:20px}.cards{grid-template-columns:1fr}.page-intro{flex-direction:column}.topbar-actions{display:none}}
    </style>
    <style>
        .content>form{max-width:1100px;padding:24px;border:1px solid var(--line);border-radius:28px;background:#fff;box-shadow:0 16px 34px rgba(25,64,59,.07)}
        .side-link.pending{color:#c7dcda}.side-link.pending::after{content:"Soon";margin-left:auto;color:#82b8b4;font-size:.62rem;letter-spacing:.05em;text-transform:uppercase}
        .card[data-coming-soon]{text-decoration:none;cursor:pointer;transition:transform .18s ease,border-color .18s ease}.card[data-coming-soon]:hover{transform:translateY(-2px);border-color:#9fc5c0}

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

        /* ---- repeatable editor rows (options, result ranges) ---- */
        .editor-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;padding:14px;border:1px solid var(--line);border-radius:16px;background:#fbfbf7}
        .editor-row + .editor-row{margin-top:10px}
        .editor-row .fld{flex:1 1 130px;min-width:0}.editor-row .fld.grow{flex:2 1 200px}.editor-row .fld.narrow{flex:0 1 92px}
        .editor-row label{margin-bottom:5px}
        .editor-row .remember{margin:0 4px 8px 0}
        .editor-row .row-remove{flex:0 0 auto}
        .add-row{margin-top:12px}

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
        .wizard-hint{font-weight:500;opacity:.85}
        .publish-panel{border-color:#b8dcd4;background:#f2f9f6}

        /* ---- read-only preview ---- */
        .preview-shell{width:min(640px,100%);margin:0 auto;display:flex;flex-direction:column;gap:16px}
        .preview-progress{height:8px;border-radius:8px;background:#e7ece9}.preview-progress>span{display:block;height:8px;border-radius:8px;background:var(--accent)}
        .preview-q{padding:16px 0;border-top:1px solid var(--line)}.preview-q:first-of-type{border-top:0;padding-top:0}
        .preview-opt{display:flex;gap:10px;align-items:center;padding:10px 13px;margin-top:7px;border:1px solid var(--line);border-radius:12px;color:var(--muted)}

        @media(max-width:640px){.split{flex-direction:column}.version-row{flex-wrap:wrap}}
    </style>
</head>
<body>
@auth
<div class="admin-shell">
    <aside class="sidebar">
        <div class="brand-block"><div class="brand-title">SheZen Harmony</div><div class="brand-subtitle">Administration</div></div>
        <nav aria-label="Administration">
            <section class="nav-section"><div class="nav-label">Overview</div><a class="side-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="side-icon">▦</span>Dashboard</a></section>
            <section class="nav-section"><div class="nav-label">Assessment</div><a class="side-link {{ (request()->routeIs('admin.questionnaires.*') || request()->routeIs('admin.questions.*')) ? 'active' : '' }}" href="{{ route('admin.questionnaires.index') }}"><span class="side-icon">☷</span>Questionnaire Management</a><a class="side-link {{ request()->routeIs('admin.student-stress.*') ? 'active' : '' }}" href="{{ route('admin.student-stress.index') }}"><span class="side-icon">◉</span>Stress Level Assessment</a></section>
            <section class="nav-section"><div class="nav-label">Resources</div><a class="side-link {{ request()->routeIs('admin.interventions.*') ? 'active' : '' }}" href="{{ route('admin.interventions.index') }}"><span class="side-icon">⌁</span>Support Content</a><a class="side-link {{ request()->routeIs('admin.wellbeing_activities.*') ? 'active' : '' }}" href="{{ route('admin.wellbeing_activities.index') }}"><span class="side-icon">▶</span>Video Activities</a><a class="side-link {{ request()->routeIs('admin.personal-guidance.*') ? 'active' : '' }}" href="{{ route('admin.personal-guidance.index') }}"><span class="side-icon">✿</span>Personal Guidance</a><a class="side-link pending" href="#" data-coming-soon="Categories"><span class="side-icon">⌗</span>Categories</a></section>
            <section class="nav-section"><div class="nav-label">Users</div><a class="side-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}"><span class="side-icon">♙</span>Registered Students</a><a class="side-link pending" href="#" data-coming-soon="Demographic Reports"><span class="side-icon">◫</span>Demographic Reports</a><a class="side-link pending" href="#" data-coming-soon="Demographic Fields"><span class="side-icon">≡</span>Demographic Fields</a><a class="side-link pending" href="#" data-coming-soon="Deletion Requests"><span class="side-icon">⌫</span>Deletion Requests</a></section>
            <section class="nav-section"><div class="nav-label">Account</div><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="side-link side-signout" type="submit"><span class="side-icon">↪</span>Sign out</button></form></section>
        </nav>
    </aside>
    <div class="main-column"><header class="page-topbar"><div><h1>@yield('page-title', trim($__env->yieldContent('title')))</h1><p class="page-kicker">Logged in as {{ auth()->user()->name }} · SheZen Harmony administration</p></div></header>@yield('body')</div>
</div>
@else
@yield('body')
@endauth
<script>
document.querySelectorAll('[data-coming-soon]').forEach(function (link) {
    link.addEventListener('click', function (event) {
        event.preventDefault();
        window.alert(link.dataset.comingSoon + ' is still in progress.');
    });
});
</script>
</body>
</html>
