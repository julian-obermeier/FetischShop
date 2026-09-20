<?php
use App\Core\Session;
use App\Core\View;

$pageTitle = $pageTitle ?? 'FetischShop';
$isAdmin = (bool) Session::get('admin_id');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

function adminNavActive(string $currentPath, string $href): string {
    if ($href === '/admin') {
        return $currentPath === '/admin' ? ' active' : '';
    }
    return str_starts_with($currentPath, $href) ? ' active' : '';
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#8f3d68">
<title><?=View::e($pageTitle)?></title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/app-icon.svg">
<link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?=$isAdmin?'admin-body':''?>">
<?php if($isAdmin):?>
<div class="admin-shell">
<aside class="admin-sidebar" id="admin-sidebar">
<div class="admin-brand"><a href="/admin">Fetisch<span>Shop</span></a><small>Administration</small></div>
<nav class="admin-sidebar-nav">
<div class="admin-nav-group"><span class="admin-nav-label">Arbeitszentrale</span>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin')?>" href="/admin"><span>⌂</span>Übersicht</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/entscheidungen')?>" href="/admin/entscheidungen"><span>✓</span>Entscheidungen</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/fristen')?>" href="/admin/fristen"><span>◷</span>Fristen</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/kalender')?>" href="/admin/kalender"><span>□</span>Kalender</a>
</div>
<div class="admin-nav-group"><span class="admin-nav-label">Geschäft</span>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/auftraege')?>" href="/admin/auftraege"><span>▥</span>Aufträge</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/verkaeuferinnen')?>" href="/admin/verkaeuferinnen"><span>♙</span>Verkäuferinnen</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/angebote')?>" href="/admin/angebote"><span>◆</span>Angebote</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/angebotsvorlagen')?>" href="/admin/angebotsvorlagen"><span>▣</span>Angebotsvorlagen</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/kategorien')?>" href="/admin/kategorien"><span>▦</span>Kategorien</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/aufgaben')?>" href="/admin/aufgaben"><span>☑</span>Aufgaben</a>
</div>
<div class="admin-nav-group"><span class="admin-nav-label">Finanzen & Versand</span>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/auszahlungen')?>" href="/admin/auszahlungen"><span>€</span>Auszahlungen</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/empfaengeradressen')?>" href="/admin/empfaengeradressen"><span>⌖</span>Empfängeradressen</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/archiv')?>" href="/admin/archiv"><span>▤</span>Archiv</a>
</div>
<div class="admin-nav-group"><span class="admin-nav-label">System</span>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/einstellungen')?>" href="/admin/einstellungen"><span>⚙</span>Einstellungen</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/system/status')?>" href="/admin/system/status"><span>●</span>Systemstatus</a>
<a class="admin-nav-link<?=adminNavActive($currentPath,'/admin/system/update')?>" href="/admin/system/update"><span>↻</span>Systemupdate</a>
</div>
</nav>
<div class="admin-sidebar-foot">
<form method="post" action="/admin/logout"><?=App\Core\Csrf::field()?><button class="admin-logout" type="submit">Abmelden</button></form>
</div>
</aside>
<div class="admin-main">
<header class="admin-topbar">
<button class="admin-menu-toggle" type="button" data-admin-menu aria-label="Menü öffnen">☰</button>
<div><b><?=View::e($pageTitle)?></b><small>FetischShop Verwaltung</small></div>
<form class="admin-search" action="/admin/suche" method="get"><input type="search" name="q" placeholder="Auftrag, E-Mail, Angebot, Tracking …"><button type="submit">Suchen</button></form>
</header>
<?php if($m=Session::pullFlash('success')):?><div class="flash success"><?=View::e($m)?></div><?php endif;?>
<?php if($m=Session::pullFlash('error')):?><div class="flash danger"><?=View::e($m)?></div><?php endif;?>
<div class="admin-content"><?php require $contentView;?></div>
</div>
</div>
<?php else:?>
<header class="site-header"><a class="brand" href="/">Fetisch<span>Shop</span></a><nav><a href="/angebote">Angebote</a><a href="/so-funktioniert-es">Ablauf</a><a href="/faq">FAQ</a><?php if(Session::get('seller_id')):?><a href="/konto">Mein Bereich</a><?php else:?><a href="/login">Login</a><?php endif;?></nav></header>
<?php if($m=Session::pullFlash('success')):?><div class="flash success"><?=View::e($m)?></div><?php endif;?>
<?php if($m=Session::pullFlash('error')):?><div class="flash danger"><?=View::e($m)?></div><?php endif;?>
<?php require $contentView;?>
<footer class="site-footer"><div><strong>FetischShop</strong><p>Diskrete Ankaufsplattform für volljährige Verkäuferinnen.</p></div><nav><a href="/kontakt">Kontakt</a><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a><a href="/bedingungen">Bedingungen</a></nav></footer>
<?php endif;?>
<script src="/assets/app.js" defer></script>
</body>
</html>
