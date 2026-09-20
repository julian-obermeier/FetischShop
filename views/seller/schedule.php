<?php
$groupLabels=[
 'overdue'=>['Überfällig','Diese Frist ist bereits vollständig abgelaufen.'],
 'now'=>['Jetzt erledigen','Die Hauptfrist ist abgelaufen; eine Nachfrist läuft noch.'],
 'today'=>['Heute','Diese Punkte sind heute fällig.'],
 'tomorrow'=>['Morgen','Diese Punkte stehen morgen an.'],
 'later'=>['Demnächst','Weitere anstehende Fristen.'],
];
$typeLabels=[
 'evidence'=>'Nachweis',
 'retake'=>'Nachaufnahme',
 'digital_submission'=>'Digitale Abgabe',
 'task'=>'Zusatzaufgabe',
 'spontaneous'=>'Fotoanforderung',
 'revision'=>'Digitale Revision',
 'damage'=>'Beschädigungsnachweis',
 'shipping'=>'Versand',
 'private_offer'=>'Privatangebot',
];
?>
<main class="section">
<div class="seller-hero"><div><span class="eyebrow">Zeitplanung</span><h1>Fristen & Kalender</h1><p>Hier siehst du, was wann fällig wird – nach Dringlichkeit sortiert.</p></div></div>

<form method="get" class="card filters">
<select name="view">
<option value="upcoming" <?=$viewMode==='upcoming'?'selected':''?>>Nächste 30 Tage</option>
<option value="week" <?=$viewMode==='week'?'selected':''?>>Wochenansicht</option>
<option value="month" <?=$viewMode==='month'?'selected':''?>>Monatsansicht</option>
</select>
<input type="date" name="date" value="<?=App\Core\View::e($anchorDate)?>">
<button class="btn primary">Anzeigen</button>
</form>

<?php foreach($groupLabels as $key=>[$title,$description]): if(empty($groups[$key]))continue;?>
<section class="seller-section-card">
<div class="section-head compact"><div><h2><?=App\Core\View::e($title)?></h2><p class="muted"><?=App\Core\View::e($description)?></p></div><span class="seller-status <?=$key==='overdue'?'danger':($key==='now'||$key==='today'?'warn':'')?>"><?=count($groups[$key])?></span></div>
<div class="seller-action-list">
<?php foreach($groups[$key] as $item):?>
<a class="seller-action" href="<?=App\Core\View::e($item['url'])?>">
<div>
<b><?=App\Core\View::e($item['title'])?></b>
<span><?php if($item['order_number']):?>Auftrag #<?=App\Core\View::e($item['order_number'])?> · <?php endif;?><?=App\Core\View::e($typeLabels[$item['event_type']]??$item['event_type'])?> · fällig <?=date('d.m.Y H:i',strtotime($item['due_at']))?><?php if(!empty($item['grace_at'])&&$item['grace_at']!==$item['due_at']):?> · Nachfrist bis <?=date('d.m.Y H:i',strtotime($item['grace_at']))?><?php endif;?></span>
</div>
<span class="seller-status <?=$key==='overdue'?'danger':($key==='now'||$key==='today'?'warn':'')?>"><?=$key==='overdue'?'Überfällig':($key==='now'?'Jetzt':'Öffnen')?></span>
</a>
<?php endforeach;?>
</div>
</section>
<?php endforeach;?>

<?php if(!array_filter($groups)):?><div class="empty">Im gewählten Zeitraum sind keine offenen Fristen vorhanden.</div><?php endif;?>

<section class="card seller-section-card">
<h2>Angezeigter Zeitraum</h2>
<p class="muted"><?=date('d.m.Y', $from->getTimestamp())?> bis <?=date('d.m.Y', $to->getTimestamp())?>. Erledigte Punkte werden aus dieser Übersicht automatisch entfernt.</p>
</section>
</main>