<?php
$fulfillmentLabels=['days'=>'Mehrere Tage','units'=>'Mehrere Einheiten','once'=>'Einmalig','digital'=>'Digitale Abgabe','combined'=>'Kombi-Auftrag'];
$componentTypeLabels=['physical'=>'Physischer Artikel','digital'=>'Digitale Abgabe'];
$evidenceCfg=json_decode($offer['evidence_json']?:'{}',true)?:[];
$windows=$evidenceCfg['windows']??[];
$regularPerDay=0;foreach($windows as $w)$regularPerDay+=(int)($w['required_count']??1);
$shippingCfg=json_decode($offer['shipping_json']?:'{}',true)?:[];
?>
<main class="section narrow">
<a class="back" href="/angebote">← Alle Angebote</a>
<article class="detail-card">
<div class="detail-top"><span class="pill"><?=App\Core\View::e($offer['category_name'])?></span><div class="price"><?=number_format((float)$offer['compensation'],2,',','.')?> €</div></div>
<h1><?=App\Core\View::e($offer['title'])?></h1>
<div class="prose"><?=nl2br(App\Core\View::e($offer['description']))?></div>

<div class="facts">
<div><span>Art des Auftrags</span><strong><?=App\Core\View::e($fulfillmentLabels[$offer['fulfillment_model']]??$offer['fulfillment_model'])?></strong></div>
<div><span>Dauer / Umfang</span><strong><?=App\Core\View::e(trim(($offer['duration_value']??'').' '.($offer['duration_unit']??'')) ?: 'siehe Beschreibung')?></strong></div>
</div>

<?php if($components):?><section class="card"><h2>Bestandteile dieses Auftrags</h2><div class="worklist"><?php foreach($components as $co):?><div class="work-row"><div><b><?=App\Core\View::e($co['title'])?></b><span><?=App\Core\View::e($co['category_name'])?> · <?=App\Core\View::e($componentTypeLabels[$co['component_type']]??$co['component_type'])?> · <?=App\Core\View::e($fulfillmentLabels[$co['fulfillment_model']]??$co['fulfillment_model'])?><?php if($co['duration_value']):?> · <?= (int)$co['duration_value'] ?> <?=App\Core\View::e($co['duration_unit']??'')?><?php endif;?></span></div><strong><?=number_format((float)$co['compensation'],2,',','.')?> €</strong></div><?php endforeach;?></div></section><?php endif;?>

<section class="card"><h2>Was dich erwartet</h2><div class="worklist">
<div class="work-row"><div><b>Grundvergütung</b><span>Zusatzoptionen werden nur dann addiert, wenn du sie auswählst.</span></div><strong><?=number_format((float)$offer['compensation'],2,',','.')?> €</strong></div>
<div class="work-row"><div><b>Pflichtnachweise</b><span><?=count($windows)?> Zeitfenster<?php if($regularPerDay):?> · insgesamt <?=$regularPerDay?> Pflichtaufnahme(n) pro regulärem Durchführungstag<?php endif;?></span></div></div>
<div class="work-row"><div><b>Zusatzaufgaben</b><span><?=count($offerTasks)?> bereits vorgeplant<?php if($offerTasks):?> · <?=App\Core\View::e(implode(', ',array_map(fn($t)=>$t['title'],$offerTasks)))?><?php endif;?></span></div></div>
<div class="work-row"><div><b>Abgabe / Versand</b><span><?php if($hasDigital):?>Digitale Bestandteile werden geschützt über die Plattform eingereicht. <?php endif;?><?=!empty($shippingCfg)?'Für physische Bestandteile sind Versandschritte hinterlegt.':'Eine konkrete Empfängeradresse wird erst in der Versandphase angezeigt.'?></span></div></div>
<div class="work-row"><div><b>Verlängerungen</b><span>Bestätigte Verstöße können nach den Auftragsregeln zusätzliche unbezahlte Durchführungstage erzeugen. Die Zuordnung erfolgt nur zum betroffenen Bestandteil.</span></div></div>
</div></section>

<?php if($seller&&$eligibilityReason):?><div class="alert danger"><strong>Dieses Angebot kann aktuell nicht angenommen werden.</strong><br><?=App\Core\View::e($eligibilityReason)?></div><?php elseif($seller):?><div class="notice"><strong>Du kannst dieses Angebot aktuell annehmen.</strong> Vor dem Absenden kannst du unten alle Optionen und Bestätigungen prüfen.</div><?php endif;?>

<form method="post" action="/konto/angebote/<?=$offer['offer_id']?>/annehmen" class="accept-box"><?=App\Core\Csrf::field()?>
<?php if($options):?><h2>Optionen</h2><div class="option-list"><?php foreach($options as $op):?><label><span><input type="checkbox" name="options[]" value="<?=$op['id']?>"> <strong><?=App\Core\View::e($op['name'])?></strong></span><span><?=App\Core\View::e($op['description'])?></span><b><?=((float)$op['price']>0?'+ '.number_format((float)$op['price'],2,',','.').' €':'kostenlos')?></b></label><?php endforeach;?></div><?php endif;?>

<?php if($hasDigital):?><label class="check rights-check"><input type="checkbox" name="rights_acceptance" value="1" required><span>Ich bestätige die Rechtevereinbarung für die digitale Abgabe. Die Rechte werden erst im Rahmen der abschließenden Prüfung entsprechend der vereinbarten Regelung freigegeben.</span></label><?php endif;?>

<div class="notice">Bei der Annahme werden Vergütung, Dauer, Nachweise, Optionen, Versand beziehungsweise digitale Abgabe und mögliche Verlängerungen verbindlich im Auftrag gespeichert. Spätere Änderungen werden separat protokolliert.</div>
<label class="check"><input type="checkbox" name="summary_confirmation" value="1" required><span>Ich habe Vergütung, Aufwand, Nachweise, Aufgaben, Optionen, Versand/Abgabe und mögliche Verlängerungstage gelesen und bestätige den Auftrag.</span></label>

<?php if($seller):?>
<?php if($eligibilityReason):?><button class="btn wide" type="button" disabled>Aktuell nicht annehmbar</button>
<?php else:?><button class="btn primary wide" type="submit">Auftrag verbindlich annehmen</button><?php endif;?>
<?php else:?><a class="btn primary wide" href="/login">Anmelden, um Auftrag anzunehmen</a><?php endif;?>
</form>

<?php if($seller && !empty($offer['is_private']) && $offer['private_offer_status']==='pending'):?><form method="post" action="/konto/angebote/<?=$offer['offer_id']?>/ablehnen" class="decline-box"><?=App\Core\Csrf::field()?><label>Ablehnungsgrund<textarea name="reason" rows="3" required></textarea></label><button class="btn wide" type="submit">Privatangebot ablehnen</button></form><?php endif;?>
</article></main>