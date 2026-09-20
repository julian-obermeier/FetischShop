<?php
$statusLabels=['precheck'=>'Vorabkontrolle','running'=>'In Durchführung','shipping'=>'Versand','digital_review'=>'Digitale Prüfung','reviewing'=>'Abschlussprüfung','completed'=>'Auszahlbar','rejected'=>'Abgelehnt','archived'=>'Archiviert','cancelled'=>'Storniert','paid'=>'Ausgezahlt'];
$phaseLabels=['preparation'=>'Vorbereitung','execution'=>'Durchführung','shipping'=>'Versand','review'=>'Prüfung','payout'=>'Auszahlung','archive'=>'Archiv'];
$phaseProgress=['preparation'=>15,'execution'=>45,'shipping'=>65,'review'=>82,'payout'=>94,'archive'=>100];
?>
<main class="section">
<div class="seller-hero"><div><span class="eyebrow">Übersicht</span><h1>Meine Aufträge</h1><p>Öffne einen Auftrag, um Nachweise, Aufgaben, Versand oder digitale Abgaben zu erledigen.</p></div><a class="btn primary" href="/angebote">Neue Angebote</a></div>
<div class="seller-action-list">
<?php foreach($orders as $o): $progress=$phaseProgress[$o['phase']]??30;?>
<a class="seller-order-card" href="/konto/auftraege/<?=$o['id']?>"><div class="top"><div><b>#<?=App\Core\View::e($o['order_number'])?> · <?=App\Core\View::e($o['title'])?></b><div class="meta"><?=App\Core\View::e($statusLabels[$o['status']]??$o['status'])?> · <?=App\Core\View::e($phaseLabels[$o['phase']]??$o['phase'])?><?=$o['component_count']>1?' · '.$o['component_count'].' Bestandteile':''?></div></div><strong><?=number_format((float)$o['current_total'],2,',','.')?> €</strong></div><div class="seller-order-progress"><span style="width:<?=$progress?>%"></span></div><div class="row-actions"><span class="seller-status <?=in_array($o['status'],['completed','archived','paid'],true)?'ok':(in_array($o['status'],['precheck','reviewing'],true)?'warn':'')?>"><?=App\Core\View::e($statusLabels[$o['status']]??$o['status'])?></span><span>Auftrag öffnen →</span></div></a>
<?php endforeach;?><?php if(!$orders):?><div class="empty">Du hast noch keine Aufträge. Unter „Neue Angebote“ kannst du verfügbare Angebote ansehen.</div><?php endif;?>
</div>
</main>