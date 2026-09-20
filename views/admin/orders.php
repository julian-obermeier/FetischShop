<?php
$statusLabels=['precheck'=>'Vorabkontrolle','running'=>'In Durchführung','shipping'=>'Versand','shipped'=>'Versendet','reviewing'=>'Abschlussprüfung','digital_review'=>'Digitale Prüfung','completed'=>'Abgeschlossen / auszahlbar','rejected'=>'Abgelehnt','archived'=>'Archiviert','cancelled'=>'Storniert','paid'=>'Ausgezahlt'];
$phaseLabels=['preparation'=>'Vorbereitung','execution'=>'Durchführung','shipping'=>'Versand','review'=>'Prüfung','payout'=>'Auszahlung','archive'=>'Archiv'];
?>
<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">Auftragsverwaltung</span><h1>Alle Aufträge</h1><p>Durchsuche laufende, abgeschlossene und archivierte Aufträge nach Verkäuferin, Status oder Phase.</p></div></div>
<form method="get" class="card filters">
<input type="search" name="q" value="<?=App\Core\View::e($filterTerm)?>" placeholder="Auftragsnummer, Name, E-Mail oder Angebot">
<select name="status"><option value="">Jeder Status</option><?php foreach($statuses as $s):?><option value="<?=App\Core\View::e($s)?>" <?=$filterStatus===$s?'selected':''?>><?=App\Core\View::e($statusLabels[$s]??$s)?></option><?php endforeach;?></select>
<select name="phase"><option value="">Jede Phase</option><?php foreach($phases as $p):?><option value="<?=App\Core\View::e($p)?>" <?=$filterPhase===$p?'selected':''?>><?=App\Core\View::e($phaseLabels[$p]??$p)?></option><?php endforeach;?></select>
<button class="btn primary">Filtern</button><a class="btn ghost" href="/admin/auftraege">Zurücksetzen</a>
</form>
<div class="table-wrap"><table><thead><tr><th>Auftrag</th><th>Verkäuferin</th><th>Status</th><th>Bestandteile</th><th>Betrag</th><th>Aktualisiert</th><th></th></tr></thead><tbody>
<?php foreach($orders as $o):?><tr>
<td><strong>#<?=App\Core\View::e($o['order_number'])?></strong><small><?=App\Core\View::e($o['title'])?></small></td>
<td><strong><?=App\Core\View::e($o['first_name'].' '.$o['last_name'])?></strong><small><?=App\Core\View::e($o['email'])?></small></td>
<td><strong><?=App\Core\View::e($statusLabels[$o['status']]??$o['status'])?></strong><small><?=App\Core\View::e($phaseLabels[$o['phase']]??$o['phase'])?></small></td>
<td><?= (int)$o['component_count'] ?><?php if((int)$o['component_count']>1):?><small><?= (int)$o['physical_count'] ?> physisch · <?= (int)$o['digital_count'] ?> digital</small><?php endif;?></td>
<td><strong><?=number_format((float)$o['current_total'],2,',','.')?> €</strong></td>
<td><?=date('d.m.Y H:i',strtotime($o['updated_at']))?></td>
<td><a class="btn" href="/admin/auftraege/<?=$o['id']?>">Öffnen</a></td>
</tr><?php endforeach;?>
</tbody></table><?php if(!$orders):?><div class="empty">Für diese Filter wurden keine Aufträge gefunden.</div><?php endif;?></div>
</main>