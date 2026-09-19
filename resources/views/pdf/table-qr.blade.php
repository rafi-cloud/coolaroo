<!DOCTYPE html>
<html lang="en-AU">
<head>
<meta charset="utf-8">
<title>Table {{ $table->table_number }} QR</title>
<style>
  body { font-family: sans-serif; text-align: center; padding: 40px; }
  h1 { font-size: 28px; margin-bottom: 4px; }
  p { color: #555; }
  .qr { margin: 30px auto; }
</style>
</head>
<body>
  <h1>Table {{ $table->table_number }}</h1>
  <p>Scan to view the menu and order</p>
  <div class="qr">{!! $qrSvg !!}</div>
</body>
</html>
