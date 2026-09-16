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

        @media(max-width:640px){.split{flex-direction:column}.version-row{flex-wrap:wrap}}

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
        .admin-login-submit{width:100%;min-height:48px;border-radius:8px;background:linear-gradient(100deg,#8b4ae9,#6d34dc);box-shadow:0 10px 19px rgba(111,52,220,.25);font-size:.9rem;transition:transform .18s,box-shadow .18s,filter .18s}
        .admin-login-submit:hover{filter:brightness(1.05);transform:translateY(-1px);box-shadow:0 13px 23px rgba(111,52,220,.3)}
        .admin-login-submit:focus-visible{outline:3px solid rgba(116,66,220,.35);outline-offset:3px}
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
        .admin-login-options{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:2px 0 20px}.admin-login-remember{margin:0;font-size:.74rem}.admin-login-forgot{color:#bd5f9e;font-size:.74rem;white-space:nowrap}.admin-login-submit{min-height:45px;border-radius:7px;background:linear-gradient(100deg,#954f91,#884887);box-shadow:0 10px 18px rgba(132,62,128,.2)}.admin-login-submit:hover{background:linear-gradient(100deg,#a75a9f,#91498e)}.admin-login-footer{margin-top:26px;padding-top:10px;border-top:1px solid #dfd6e4;color:#8a7897;font-size:.72rem;text-align:center}.admin-login-footer span{padding:0 5px;color:#d1c2d7}.admin-login-footer em{font-style:italic}
        @media(max-width:840px){.admin-login-floral-logo{width:min(235px,70%)}.admin-login-tagline{margin-bottom:24px}}@media(max-width:420px){.admin-login-options{align-items:flex-start;flex-direction:column;gap:8px}.admin-login-forgot{align-self:flex-end}}

        /* Final wireframe proportions for the public administrator sign-in page. */
        .admin-login-shell{min-height:100svh;padding:clamp(20px,3.5vh,40px) clamp(18px,3vw,32px);background:radial-gradient(circle at 50% 49%,rgba(247,203,239,.62),transparent 31%),radial-gradient(circle at 16% 55%,rgba(236,180,245,.5),transparent 25%),#fff}
        .admin-login-card{grid-template-columns:1fr 1fr;width:min(1000px,100%);min-height:0;height:min(620px,calc(100svh - 64px));border-radius:32px;border:1px solid rgba(255,255,255,.9);box-shadow:0 26px 64px rgba(160,76,160,.28)}
        .admin-login-form-pane{padding:clamp(28px,4vh,42px) clamp(32px,4.5vw,60px);background:linear-gradient(145deg,#fff 0%,#fffafd 100%)}.admin-login-content{width:min(100%,430px)}
        .admin-login-floral-logo{width:135px;max-width:54%;height:135px;margin:0 auto 16px;object-fit:contain}.admin-login-card h1{font-size:clamp(1.85rem,2.6vw,2.35rem);letter-spacing:-.055em}.admin-login-subtitle{margin:10px auto 20px;max-width:370px;font-size:.94rem;line-height:1.38}
        .admin-login-form input:not([type="checkbox"]){min-height:54px;border-color:#d8c4df;border-radius:9px;font-size:.92rem}.admin-login-input-wrap{margin-bottom:15px}.admin-login-form label:not(.admin-login-remember){margin-bottom:7px;font-size:.82rem}.admin-login-field-icon,.admin-login-password-icon{width:20px;height:20px}.admin-login-field-icon{left:17px}.admin-login-password-icon{right:17px}.admin-login-form input:not([type="checkbox"]){padding-left:52px;padding-right:52px}
        .admin-login-password-icon{right:8px;display:grid;width:40px;height:40px;place-items:center;padding:0;border:0;border-radius:8px;background:transparent;cursor:pointer;pointer-events:auto}.admin-login-password-icon svg{width:20px;height:20px}.admin-login-password-icon:hover{color:#884887}.admin-login-password-icon:focus-visible{outline:2px solid #884887;outline-offset:2px}.admin-login-password-icon[aria-pressed="true"] .admin-login-eye-slash{display:none}
        .admin-login-options{justify-content:flex-start;margin:3px 0 15px}.admin-login-remember{font-size:.87rem;font-weight:650}.admin-login-remember input{width:19px;height:19px;border-radius:4px}.admin-login-submit{min-height:54px;border-radius:10px;font-size:1rem;background:linear-gradient(100deg,#ad5b9f,#803a82);box-shadow:0 13px 22px rgba(129,54,122,.2)}.admin-login-submit span{margin-left:9px;font-size:1.35rem;font-weight:400;line-height:0}.admin-login-footer{margin-top:20px;padding-top:13px;font-size:.78rem}
        .admin-login-visual-pane{padding:16px;background:radial-gradient(circle at 50% 50%,#fff 0%,#fff8fc 49%,#fde9f7 100%)}.admin-login-illustration{width:min(110%,560px);max-height:min(480px,66svh);object-fit:contain;filter:drop-shadow(0 22px 18px rgba(155,84,145,.14))}.admin-login-orb-one{width:240px;height:240px;top:-110px;right:-95px;background:rgba(215,137,201,.34)}.admin-login-orb-two{width:260px;height:260px;bottom:-135px;left:-125px;background:rgba(227,172,231,.3)}
        @media(max-width:840px){.admin-login-shell{padding:18px}.admin-login-card{grid-template-columns:1fr;max-width:580px;height:auto}.admin-login-form-pane{min-height:0}.admin-login-visual-pane{display:none}}@media(max-width:420px){.admin-login-shell{padding:0}.admin-login-card{border-radius:0}.admin-login-form-pane{padding:30px 24px}.admin-login-floral-logo{width:123px;height:123px}}
        @media(max-height:700px) and (min-width:841px){.admin-login-shell{padding:16px}.admin-login-card{height:min(620px,calc(100svh - 32px))}.admin-login-form-pane{padding:22px 48px}.admin-login-floral-logo{width:112px;height:112px;margin-bottom:10px}.admin-login-subtitle{margin-bottom:14px}.admin-login-form input:not([type="checkbox"]){min-height:48px}.admin-login-input-wrap{margin-bottom:10px}.admin-login-options{margin-bottom:10px}.admin-login-submit{min-height:48px}.admin-login-footer{margin-top:13px;padding-top:10px}.admin-login-illustration{max-height:420px}}
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
            <section class="nav-section"><div class="nav-label">Wellbeing Activities</div><a class="side-link {{ request()->routeIs('admin.interventions.*') ? 'active' : '' }}" href="{{ route('admin.interventions.index') }}"><span class="side-icon">⌁</span>Support Content</a><a class="side-link {{ request()->routeIs('admin.wellbeing_activities.*') ? 'active' : '' }}" href="{{ route('admin.wellbeing_activities.index') }}"><span class="side-icon">▶</span>Video Activities</a></section>
            <section class="nav-section"><div class="nav-label">Personal Guidance</div><a class="side-link {{ request()->routeIs('admin.personal-guidance.*') ? 'active' : '' }}" href="{{ route('admin.personal-guidance.index') }}"><span class="side-icon">✿</span>Personal Guidance</a></section>
            <section class="nav-section"><div class="nav-label">Resource</div><a class="side-link {{ request()->routeIs('admin.resources.*') ? 'active' : '' }}" href="{{ route('admin.resources.index') }}"><span class="side-icon">☎</span>Helpline Resources</a></section>
            <section class="nav-section"><div class="nav-label">Positive Engagement</div><a class="side-link {{ request()->routeIs('admin.positive-engagement.*') && ! request()->routeIs('admin.positive-engagement.games.*') && ! request()->routeIs('admin.positive-engagement.games-quizzes.*') ? 'active' : '' }}" href="{{ route('admin.positive-engagement.index') }}"><span class="side-icon">◇</span>Motivational Content</a><a class="side-link {{ request()->routeIs('admin.positive-engagement.games.*') ? 'active' : '' }}" href="{{ route('admin.positive-engagement.games.index') }}"><span class="side-icon">♢</span>Games</a><a class="side-link {{ request()->routeIs('admin.positive-engagement.games-quizzes.*') ? 'active' : '' }}" href="{{ route('admin.positive-engagement.games-quizzes.index') }}"><span class="side-icon">▤</span>Quizzes</a></section>
            <section class="nav-section"><div class="nav-label">Shezen Chat Buddy</div><a class="side-link {{ request()->routeIs('admin.chatbuddy.*') ? 'active' : '' }}" href="{{ route('admin.chatbuddy.index') }}"><span class="side-icon">☺</span>Shezen Chat Buddy</a></section>
            <section class="nav-section"><div class="nav-label">Users</div><a class="side-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}"><span class="side-icon">♙</span>Registered Students</a><a class="side-link pending" href="#" data-coming-soon="Demographic Reports"><span class="side-icon">◫</span>Demographic Reports</a></section>
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
