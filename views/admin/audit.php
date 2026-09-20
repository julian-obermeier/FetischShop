<?php
$eventLabels=[
 'order_accepted'=>'Auftrag angenommen',
 'order_confirmation_email_sent'=>'Auftragsbestätigung versendet',
 'order_confirmation_email_failed'=>'Auftragsbestätigung konnte nicht versendet werden',
 'options_changed'=>'Optionen geändert',
 'order_adjustment'=>'Auftragswert angepasst',
 'order_archived_after_payout'=>'Auftrag nach Auszahlung archiviert',
 'private_offer_declined'=>'Privatangebot abgelehnt',
 'seller_consented'=>'Rechtevereinbarung bestätigt',
 'revision_requested'=>'Digitale Revision angefordert',
 'rights_granted'=>'Digitale Rechte freigegeben',
 'rights_not_granted'=>'Digitale Rechte nicht freigegeben',
 'admin_download'=>'Digitale Version heruntergeladen',
];
$actorLabels=['seller'=>'Verkäuferin','admin'=>'Administrator','system'=>'System'];
$keyLabels=[
 'total'=>'Gesamtbetrag',
 'components'=>'Bestandteile',
 'selected'=>'Gewählte Optionen',
 'delta'=>'Änderung',
 'id'=>'Datensatz',
 'type'=>'Art',
 'label'=>'Bezeichnung',
 'amount'=>'Betrag',
 'effective_day_no'=>'Wirksam ab Tag',
 'payout_request_id'=>'Auszahlungsantrag',
 'approved_amount'=>'Freigegebener Betrag',
 'offer_id'=>'Angebot',
 'reason'=>'Grund',
 'digital_component_id'=>'Digitaler Bestandteil',
 'clause_version'=>'Version der Rechtevereinbarung',
 'note'=>'Hinweis',
];
$moneyKeys=['total','delta','amount','approved_amount'];
?>
<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">Nachvollziehbarkeit</span><h1>Änderungsprotokoll</h1><p>Wichtige Auftrags-, Konto-, Rechte- und Systemereignisse chronologisch nachvollziehen.</p></div></div>

<form method="get" class="card filters">
<input type="search" name="q" value="<?=App\Core\View::e($filterTerm)?>" placeholder="Auftrag, Verkäuferin, E-Mail oder Ereignis">
<select name="actor"><option value="">Alle Quellen</option><option value="seller" <?=$filterActor==='seller'?'selected':''?>>Verkäuferin</option><option value="admin" <?=$filterActor==='admin'?'selected':''?>>Administrator</option><option value="system" <?=$filterActor==='system'?'selected':''?>>System</option></select>
<select name="event"><option value="">Alle Ereignisse</option><?php foreach($eventTypes as $type):?><option value="<?=App\Core\View::e($type)?>" <?=$filterEvent===$type?'selected':''?>><?=App\Core\View::e($eventLabels[$type]??ucfirst(str_replace('_',' ',$type)))?></option><?php endforeach;?></select>
<input type="date" name="from" value="<?=App\Core\View::e($filterFrom)?>">
<input type="date" name="to" value="<?=App\Core\View::e($filterTo)?>">
<button class="btn primary">Filtern</button>
<a class="btn ghost" href="/admin/protokoll">Zurücksetzen</a>
</form>

<div class="worklist">
<?php foreach($events as $e):?>
<div class="card">
<div class="work-row"><div><b><?=App\Core\View::e($eventLabels[$e['event_type']]??ucfirst(str_replace('_',' ',$e['event_type'])))?></b><span><?=date('d.m.Y H:i:s',strtotime($e['created_at']))?> · ausgelöst durch <?=App\Core\View::e($actorLabels[$e['actor_type']]??$e['actor_type'])?><?php if($e['order_number']):?> · Auftrag #<?=App\Core\View::e($e['order_number'])?><?php endif;?><?php if($e['email']):?> · <?=App\Core\View::e(trim(($e['first_name']??'').' '.($e['last_name']??'')))?><?php endif;?></span></div><?php if($e['order_id']):?><a class="btn ghost" href="/admin/auftraege/<?=$e['order_id']?>">Auftrag öffnen</a><?php endif;?></div>
<?php if($e['payload']):?><div class="worklist"><?php foreach($e['payload'] as $key=>$value): if($value===null||$value===''||is_array($value))continue;?><div class="work-row"><div><span><?=App\Core\View::e($keyLabels[$key]??ucfirst(str_replace('_',' ',$key)))?></span></div><strong><?php if(in_array($key,$moneyKeys,true)&&is_numeric($value)):?><?=number_format((float)$value,2,',','.')?> €<?php else:?><?=App\Core\View::e((string)$value)?><?php endif;?></strong></div><?php endforeach;?></div><?php endif;?>
</div>
<?php endforeach;?>
<?php if(!$events):?><div class="empty">Für diese Filter wurden keine Protokolleinträge gefunden.</div><?php endif;?>
</div>
</main>