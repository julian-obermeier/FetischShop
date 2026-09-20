<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">Arbeitszentrale</span><h1>Was heute wichtig ist</h1><p>Offene Prüfungen, anstehende Fristen und schnelle Zugriffe auf die wichtigsten Bereiche.</p></div><div class="actions"><a class="btn primary" href="/admin/entscheidungen">Offene Entscheidungen</a><a class="btn" href="/admin/kalender">Kalender</a></div></div>

<div class="admin-stat-grid">
<?php $stats=[
 ['prechecks','Vorabkontrollen','Noch zu prüfen','/admin/entscheidungen?type=Vorabkontrolle'],
 ['evidence','Nachweise','Noch zu prüfen','/admin/entscheidungen?type=Nachweis'],
 ['violations','Verstöße','Entscheidung offen','/admin/entscheidungen?type=Verstoß'],
 ['damage','Beschädigungen','In Bearbeitung','/admin/entscheidungen?type=Beschädigung'],
 ['revisions','Revisionen','Offene Runden','/admin/entscheidungen?type=Revision'],
 ['payouts','Auszahlungen','Offen / in Prüfung','/admin/auszahlungen'],
 ['support','Support','Offen / wartet auf Admin','/admin/support']
];foreach($stats as [$k,$label,$sub,$url]):?><a class="admin-stat" href="<?=$url?>"><span><?=App\Core\View::e($label)?></span><strong><?= (int)$counts[$k] ?></strong><small><?=App\Core\View::e($sub)?></small></a><?php endforeach;?>
</div>

<div class="admin-work-grid">
<section class="card"><div class="section-head compact"><div><h2>Nächste 24 Stunden</h2><p class="muted">Die wichtigsten Termine und Fristen im direkten Überblick.</p></div><a class="btn ghost" href="/admin/fristen">Alle Fristen</a></div>
<div class="worklist"><?php foreach($due as $d):?><div class="work-row"><div><b><?=App\Core\View::e($d['title'])?></b><span><?=App\Core\View::e($d['event_type'])?> · <?=date('d.m.Y H:i',strtotime($d['starts_at']))?></span></div><?php if($d['order_id']):?><a class="btn ghost" href="/admin/auftraege/<?=$d['order_id']?>">Auftrag öffnen</a><?php endif;?></div><?php endforeach;?><?php if(!$due):?><div class="empty">In den nächsten 24 Stunden steht nichts Dringendes an.</div><?php endif;?></div>
</section>

<aside class="card"><h2>Schnellzugriff</h2><div class="admin-quick-grid">
<a class="admin-quick-card" href="/admin/angebote"><b>Angebote</b><span>Angebote erstellen und bearbeiten</span></a>
<a class="admin-quick-card" href="/admin/verkaeuferinnen"><b>Verkäuferinnen</b><span>Konten, Stammdaten und Verlauf</span></a>
<a class="admin-quick-card" href="/admin/auszahlungen"><b>Auszahlungen</b><span>Auszahlungsanträge prüfen</span></a>
<a class="admin-quick-card" href="/admin/einstellungen"><b>Einstellungen</b><span>Plattformweite Einstellungen</span></a>
<a class="admin-quick-card" href="/admin/system/status"><b>Systemstatus</b><span>Technik, Cron und Datenbank prüfen</span></a>
<a class="admin-quick-card" href="/admin/system/update"><b>Systemupdate</b><span>Offene Datenbankupdates ausführen</span></a>
<a class="admin-quick-card" href="/admin/support"><b>Support</b><span>Supporttickets beantworten</span></a>
</div></aside>
</div>
</main>