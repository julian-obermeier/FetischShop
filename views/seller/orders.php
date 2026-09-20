<?php
$statusLabels=['precheck'=>'Vorabkontrolle','running'=>'In Durchführung','shipping'=>'Versand','digital_review'=>'Digitale Prüfung','reviewing'=>'Abschlussprüfung','completed'=>'Auszahlbar','rejected'=>'Abgelehnt','archived'=>'Archiviert','cancelled'=>'Storniert','paid'=>'Ausgezahlt'];
$phaseLabels=['preparation'=>'Vorbereitung','execution'=>'Durchführung','shipping'=>'Versand','review'=>'Prüfung','payout'=>'Auszahlung','archive'=>'Archiv'];
$phaseProgress=['preparation'=>15,'execution'=>45,'shipping'=>65,'review'=>82,'payout'=>94,'archive'=>100];
?>
<main class="section">
<div class="seller-hero"><div><span class="eyebrow">Auftragsübersicht</span><h1>Meine Aufträge</h1><p>Alle laufenden, freigegebenen und historischen Aufträge an einem Ort.</p></div><a class="btn primary" href="/angebote">Neue Angebote</a></div>

<div class="seller-kpi-grid">
<a class="seller-kpi" href="/konto/auftraege?filter=all"><span>Alle</span><strong><?= (int)($counts['total']??0) ?></strong><small>insgesamt</small></a>
<a class="seller-kpi" href="/konto/auftraege?filter=active"><span>Aktiv</span><strong><?= (int)($counts['active_count']??0) ?></strong><small>laufend</small></a>
<a class="seller-kpi" href="/konto/auftraege?filter=payout"><span>Auszahlung</span><strong><?= (int)($counts['payout_count']??0) ?></strong><small>bereit zur Auszahlung</small></a>
<a class="seller-kpi" href="/konto/auftraege?filter=archive"><span>Archiv</span><strong><?= (int)($counts['archive_count']??0) ?></strong><small>abgeschlossen</small></a>
</div>

<form method="get" class="card filters seller-section-card">
<input type="search" name="q" value="<?=App\Core\View::e($filterTerm)?>" placeholder="Auftrag oder Auftragsnummer suchen">
<select name="filter"><option value="all" <?=$filterMode==='all'?'selected':''?>>Alle Aufträge</option><option value="active" <?=$filterMode==='active'?'selected':''?>>Nur laufende</option><option value="payout" <?=$filterMode==='payout'?'selected':''?>>Auszahlung</option><option value="archive" <?=$filterMode==='archive'?'selected':''?>>Archiv</option></select>
<button class="btn primary">Filtern</button>
<a class="btn ghost" href="/konto/auftraege">Zurücksetzen</a>
</form>

<div class="seller-action-list">
<?php foreach($orders as $o): $progress=$phaseProgress[$o['phase']]??30;?>
<a class="seller-order-card" href="/konto/auftraege/<?=$o['id']?>"><div class="top"><div><b>#<?=App\Core\View::e($o['order_number'])?> · <?=App\Core\View::e($o['title'])?></b><div class="meta"><?=App\Core\View::e($statusLabels[$o['status']]??$o['status'])?> · <?=App\Core\View::e($phaseLabels[$o['phase']]??$o['phase'])?><?=$o['component_count']>1?' · '.$o['component_count'].' Bestandteile':''?></div></div><strong><?=number_format((float)$o['current_total'],2,',','.')?> €</strong></div><div class="seller-order-progress"><span style="width:<?=$progress?>%"></span></div><div class="row-actions"><span class="seller-status <?=in_array($o['status'],['completed','archived','paid'],true)?'ok':(in_array($o['status'],['precheck','reviewing'],true)?'warn':'')?>"><?=App\Core\View::e($statusLabels[$o['status']]??$o['status'])?></span><span>Auftrag öffnen →</span></div></a>
<?php endforeach;?><?php if(!$orders):?><div class="empty">Für diese Suche oder diesen Filter wurden keine Aufträge gefunden.</div><?php endif;?>
</div>
</main>