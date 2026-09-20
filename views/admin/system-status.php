<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">System</span><h1>Systemstatus</h1><p>Technischer Zustand der Anwendung in verständlicher Form.</p></div><div class="actions"><a class="btn ghost" href="/admin/system/update">Systemupdate</a></div></div>
<div class="worklist"><?php foreach($checks as $check):?><div class="work-row"><div><b><?=App\Core\View::e($check['label'])?></b><span><?=App\Core\View::e($check['value'])?></span></div><strong><?=$check['ok']?'OK':'Prüfen'?></strong></div><?php endforeach;?></div>

<section class="card"><h2>Ausstehende Datenbank-Updates</h2><?php if($pendingMigrations):?><ul><?php foreach($pendingMigrations as $m):?><li><?=App\Core\View::e(basename($m))?></li><?php endforeach;?></ul><p><a class="btn primary" href="/admin/system/update">Updates ausführen</a></p><?php else:?><p class="muted">Die Datenbank ist aktuell.</p><?php endif;?></section>

<section class="card"><h2>Letzter Cron-Lauf</h2>
<?php if($schedulerResult): $labels=[
'evidence_violations'=>'Fehlende Nachweise',
'evidence_retake_violations'=>'Versäumte Nachaufnahmen',
'task_violations'=>'Versäumte Zusatzaufgaben',
'spontaneous_violations'=>'Versäumte Spontanfotos',
'damage_violations'=>'Versäumte Schadensnachforderungen',
'revision_violations'=>'Versäumte Revisionen',
'shipping_violations'=>'Versäumte Versandschritte',
'expired_private_offers'=>'Abgelaufene Privatangebote',
'reminders'=>'Erzeugte Erinnerungen',
'cleanup'=>'Bereinigte Datensätze'
];?>
<div class="worklist"><?php foreach($labels as $key=>$label):?><div class="work-row"><div><b><?=App\Core\View::e($label)?></b></div><strong><?= (int)($schedulerResult[$key]??0) ?></strong></div><?php endforeach;?></div>
<?php else:?><p class="muted">Noch kein Cron-Ergebnis gespeichert.</p><?php endif;?></section>
</main>