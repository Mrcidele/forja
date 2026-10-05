<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Forja')</title>
    <style>
        body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: #1c1917; background: #fafaf9; }
        main { max-width: 640px; margin: 15vh auto; padding: 0 16px; }
        h1 { font-size: 2.5rem; margin: 0 0 .5rem; }
        code { background: #e7e5e4; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<main>
@yield('content')
</main>
</body>
</html>
