<!-- top of the site, nav header
It creates the start of the page so every screen shares the same setup.

Every module page should not manually recreate this.

Instead, the module view should include the shared header. -->

<!-- Typhon Cath CRM | Search | Current User | Logout -->
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php $layoutTitle = $layoutTitle ?? ''; ?>
    <title><?= htmlspecialchars($layoutTitle !== '' ? $layoutTitle . ' — Typhon Cath CRM' : 'Typhon Cath CRM') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Optional Bootstrap CDN for class project usage -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/global.css" rel="stylesheet">
</head>
<body>
