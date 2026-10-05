<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Forja')</title>
    <style>
        body { margin: 0; font: 15px/1.5 system-ui, sans-serif; color: #1c1917; background: #fafaf9; }
        main { max-width: 960px; margin: 32px auto; padding: 0 16px; }
        .flash { background: #dcfce7; padding: 8px 12px; border-radius: 6px; }
        .new-task { display: flex; gap: 8px; margin: 16px 0; flex-wrap: wrap; }
        .new-task input { flex: 1; min-width: 200px; }
        input, select, button { font: inherit; padding: 6px 10px; }
        .board { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .column { background: #f5f5f4; border-radius: 8px; padding: 12px; }
        .column h2 { font-size: 15px; margin: 0 0 8px; }
        article { background: #fff; border-radius: 6px; padding: 8px; margin-bottom: 8px; box-shadow: 0 1px 2px #0001; }
        article small { display: block; color: #78716c; }
    </style>
</head>
<body>
<main>
@yield('content')
</main>
</body>
</html>
