<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') &middot; SheZen Harmony</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #30293a; background: #f8f5fa; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        a { color: inherit; }
        .shell { min-height: 100vh; }
        .topbar { display: flex; align-items: center; justify-content: space-between; padding: 1rem 2rem; background: #fff; border-bottom: 1px solid #e8e0ed; }
        .brand { font-size: 1.1rem; font-weight: 750; color: #5d4779; }
        .content { width: min(1100px, calc(100% - 2rem)); margin: 2.5rem auto; }
        .nav { display: flex; gap: 1rem; align-items: center; }
        .nav a { color: #5d4779; font-weight: 650; text-decoration: none; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
        .card { padding: 1.25rem; border: 1px solid #e8e0ed; border-radius: 16px; background: #fff; box-shadow: 0 8px 30px rgba(75, 55, 95, .06); }
        .metric { margin-top: .5rem; font-size: 2rem; font-weight: 750; color: #6e5a8a; }
        .muted { color: #746d7c; }
        .button { border: 0; border-radius: 10px; padding: .7rem 1rem; color: #fff; background: #6e5a8a; font: inherit; font-weight: 650; cursor: pointer; }
        .button-link { color: #6e5a8a; background: transparent; }
        .auth-wrap { display: grid; min-height: 100vh; place-items: center; padding: 1rem; }
        .auth-card { width: min(430px, 100%); padding: 2rem; border: 1px solid #e8e0ed; border-radius: 20px; background: #fff; box-shadow: 0 14px 45px rgba(75, 55, 95, .1); }
        label { display: block; margin: 1rem 0 .4rem; font-weight: 650; }
        input[type=email], input[type=password], input[type=text], input[type=number], input[type=url], select, textarea { width: 100%; padding: .8rem; border: 1px solid #cfc4d7; border-radius: 10px; font: inherit; }
        textarea { min-height: 7rem; resize: vertical; }
        .error { margin-top: .35rem; color: #a12f43; font-size: .9rem; }
        .remember { display: flex; align-items: center; gap: .5rem; margin: 1rem 0; }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; }
        .button-secondary { color: #5d4779; background: #eee8f2; text-decoration: none; }
        .button-danger { background: #9b3346; }
        .table-wrap { overflow-x: auto; background: #fff; border: 1px solid #e8e0ed; border-radius: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .85rem; border-bottom: 1px solid #eee8f2; text-align: left; vertical-align: top; }
        .field-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
        .status { padding: .8rem 1rem; margin-bottom: 1rem; border-radius: 10px; background: #e9f5ec; }
        .errors { color: #8d293d; background: #fff0f2; padding: 1rem 2rem; border-radius: 10px; }
    </style>
</head>
<body>
    @yield('body')
</body>
</html>
