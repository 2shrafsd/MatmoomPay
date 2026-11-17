<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} · متجر</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #fff; color: #1c1917;
               font-family: "IBM Plex Sans Arabic", system-ui, Tahoma, sans-serif; text-align: center; padding: 24px; box-sizing: border-box; }
        h1 { font-size: 1.5rem; margin: 0 0 8px; }
        p { margin: 0; color: #78716c; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $heading }}</h1>
        <p>{{ $message }}</p>
    </main>
</body>
</html>
