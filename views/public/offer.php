<?php
use App\Core\View;
use App\Core\Csrf;

$fulfillmentLabels=['days'=>'Mehrere Tage','units'=>'Mehrere Einheiten','once'=>'Einmalig','digital'=>'Digitale Abgabe','combined'=>'Kombi-Auftrag'];
$componentTypeLabels=['physical'=>'Physischer Artikel','digital'=>'Digitale Abgabe'];
$evidenceCfg=json_decode($offer['evidence_json']?:'{}',true)?:[];
$windows=$evidenceCfg['windows']??[];
$regularPerDay=0;foreach($windows as $w)$regularPerDay+=(int)($w['required_count']??1);
$shippingCfg=json_decode($offer['shipping_json']?:'{}',true)?:[];
$shippingSteps=(json_decode($offer['end_workflow_json']?:'{}',true)?:[])['steps']??[];
$durationText=trim(($offer['duration_value']??'').' '.($offer['duration_unit']??'')) ?: 'siehe Beschreibung';
?>
<main class="section narrow offer-detail-page">
<a class="back offer-back" href="/angebote">← Alle Angebote</a>

<article class="detail-card offer-detail-card">
<header class="offer-detail-hero">
    <div class="offer-detail-copy">
        <div class="offer-detail-badges">
            <span class="pill"><?=View::e($offer['category_name'])?></span>
            <span class="seller-status"><?=View::e($fulfillmentLabels[$offer['fulfillment_model']]??$offer['fulfillment_model'])?></span>
        </div>
        <h1><?=View::e($offer['title'])?></h1>
        <p class="offer-lead"><?=nl2br(View::e($offer['description']))?></p>
    </div>
    <div class="offer-price-block">
        <small>Grundvergütung</small>
        <strong><?=number_format((float)$offer['compensation'],2,',','.')?> €</strong>
    </div>
</header>

<div class="offer-facts">
    <div class="offer-fact-card">
        <span class="offer-fact-icon">◈</span>
        <div><small>Art des Auftrags</small><strong><?=View::e($fulfillmentLabels[$offer['fulfillment_model']]??$offer['fulfillment_model'])?></strong></div>
    </div>
    <div class="offer-fact-card">
        <span class="offer-fact-icon">◷</span>
        <div><small>Dauer / Umfang</small><strong><?=View::e($durationText)?></strong></div>
    </div>
</div>

<?php if($components):?>
<section class="offer-premium-card">
    <div class="offer-section-heading"><span>▦</span><div><h2>Bestandteile dieses Auftrags</h2><p>Bei Kombi-Aufträgen wird jeder Bestandteil separat verwaltet.</p></div></div>
    <div class="offer-component-list">
    <?php foreach($components as $co):?>
        <div class="offer-component-row">
            <div>
                <b><?=View::e($co['title'])?></b>
                <span><?=View::e($co['category_name'])?> · <?=View::e($componentTypeLabels[$co['component_type']]??$co['component_type'])?> · <?=View::e($fulfillmentLabels[$co['fulfillment_model']]??$co['fulfillment_model'])?><?php if($co['duration_value']):?> · <?= (int)$co['duration_value'] ?> <?=View::e($co['duration_unit']??'')?><?php endif;?></span>
            </div>
            <strong><?=number_format((float)$co['compensation'],2,',','.')?> €</strong>
        </div>
    <?php endforeach;?>
    </div>
</section>
<?php endif;?>

<section class="offer-premium-card">
    <div class="offer-section-heading"><span>☷</span><div><h2>Was dich erwartet</h2><p>Die wichtigsten Eckdaten dieses Auftrags auf einen Blick.</p></div></div>

    <div class="offer-summary-list">
        <div class="offer-summary-row">
            <span class="offer-summary-icon">€</span>
            <div class="offer-summary-copy"><b>Grundvergütung</b><span>Dieser Betrag ist für den Auftrag bereits fest eingeplant.</span></div>
            <strong class="offer-summary-value"><?=number_format((float)$offer['compensation'],2,',','.')?> €</strong>
        </div>

        <div class="offer-summary-row">
            <span class="offer-summary-icon">＋</span>
            <div class="offer-summary-copy"><b>Zusatzoptionen</b><span>Werden nur dann addiert, wenn du sie unten auswählst.</span></div>
            <strong class="offer-summary-value"><?=count($options)?> verfügbar</strong>
        </div>

        <div class="offer-summary-row">
            <span class="offer-summary-icon">▤</span>
            <div class="offer-summary-copy"><b>Pflichtnachweise</b><span><?=count($windows)?> Zeitfenster<?php if($regularPerDay):?> · insgesamt <?=$regularPerDay?> Pflichtaufnahme<?= $regularPerDay===1?'':'n' ?> pro regulärem Durchführungstag<?php endif;?></span></div>
            <strong class="offer-summary-value"><?=count($windows)?></strong>
        </div>

        <div class="offer-summary-row">
            <span class="offer-summary-icon">☑</span>
            <div class="offer-summary-copy"><b>Zusatzaufgaben</b><span><?=count($offerTasks)?> bereits vorgeplant<?php if($offerTasks):?> · <?=View::e(implode(', ',array_map(fn($t)=>$t['title'],$offerTasks)))?><?php endif;?></span></div>
            <strong class="offer-summary-value"><?=count($offerTasks)?></strong>
        </div>

        <div class="offer-summary-row">
            <span class="offer-summary-icon">⌁</span>
            <div class="offer-summary-copy"><b>Abgabe / Versand</b><span><?php if($hasDigital):?>Digitale Bestandteile werden geschützt über die Plattform eingereicht. <?php endif;?><?=!empty($shippingCfg)||$shippingSteps?'Für physische Bestandteile sind Versandschritte hinterlegt.':'Die konkrete Empfängeradresse wird erst in der Versandphase angezeigt.'?></span></div>
            <strong class="offer-summary-value"><?=count($shippingSteps)?> Schritt<?=count($shippingSteps)===1?'':'e'?></strong>
        </div>

        <div class="offer-summary-row">
            <span class="offer-summary-icon">↻</span>
            <div class="offer-summary-copy"><b>Verlängerungen</b><span>Bestätigte Verstöße können nach den Auftragsregeln zusätzliche unbezahlte Durchführungstage erzeugen.</span></div>
            <strong class="offer-summary-value">regelbasiert</strong>
        </div>

        <div class="offer-summary-row">
            <span class="offer-summary-icon">◎</span>
            <div class="offer-summary-copy"><b>Zuordnung</b><span>Verlängerungen, Nachweise und Zusatzanforderungen werden nur dem tatsächlich betroffenen Bestandteil zugeordnet.</span></div>
            <strong class="offer-summary-value"><?=count($components)>1?'Kombi':'direkt'?></strong>
        </div>
    </div>
</section>

<?php if($seller&&$eligibilityReason):?>
<div class="offer-status-banner blocked">
    <span class="offer-status-icon">!</span>
    <div><strong>Dieses Angebot kann aktuell nicht angenommen werden.</strong><p><?=View::e($eligibilityReason)?></p></div>
</div>
<?php elseif($seller):?>
<div class="offer-status-banner">
    <span class="offer-status-icon">i</span>
    <div><strong>Dieses Angebot kann in deinen Warenkorb.</strong><p>Wähle jetzt deine Optionen. Die verbindlichen Bestätigungen erfolgen erst beim gemeinsamen Checkout.</p></div>
</div>
<?php endif;?>

<form method="post" action="/konto/warenkorb/<?=$offer['offer_id']?>" class="accept-box offer-accept-box"><?=Csrf::field()?>

<?php if($options):?>
<section class="offer-options-section">
    <div class="offer-section-heading"><span>⚙</span><div><h2>Optionen</h2><p>Wähle die Zusatzleistungen, die für dieses Angebot gelten sollen.</p></div></div>
    <div class="offer-option-list">
    <?php foreach($options as $op): $req=json_decode($op['requirements_json']?:'{}',true)?:[];$reqText=trim((string)($req['text']??''));?>
        <label class="offer-option-card">
            <div class="offer-option-main">
                <input type="checkbox" name="options[]" value="<?=$op['id']?>" data-option-price="<?=View::e((string)(float)$op['price'])?>">
                <div>
                    <b><?=View::e($op['name'])?></b>
                    <?php if(trim((string)$op['description'])!==''):?><span><?=View::e($op['description'])?></span><?php endif;?>
                    <?php if($reqText!==''):?><small><?=View::e($reqText)?></small><?php endif;?>
                </div>
            </div>
            <strong class="offer-option-price"><?=((float)$op['price']>0?'+ '.number_format((float)$op['price'],2,',','.').' €':'kostenlos')?></strong>
        </label>
    <?php endforeach;?>
    </div>
</section>
<?php endif;?>

<div class="offer-total-bar" data-offer-total data-base-total="<?=View::e((string)(float)$offer['compensation'])?>">
    <div><small>Vergütung für dieses Angebot</small><strong data-offer-total-value><?=number_format((float)$offer['compensation'],2,',','.')?> €</strong></div>
    <span data-offer-option-count>Keine Zusatzoption ausgewählt</span>
</div>

<div class="offer-binding-note">
    <span>🛒</span>
    <div><b>Mehrere Angebote gemeinsam abschließen</b><p>Du kannst weitere Angebote hinzufügen. Erst im Warenkorb bestätigst du alle Positionen gemeinsam; anschließend entsteht eine einzige Auftragsnummer.</p></div>
</div>

<?php if($seller):?>
    <?php if($eligibilityReason):?><button class="btn wide" type="button" disabled>Aktuell nicht verfügbar</button>
    <?php else:?><button class="btn primary wide offer-accept-button" type="submit">In den Warenkorb</button><?php endif;?>
<?php else:?><a class="btn primary wide offer-accept-button" href="/login">Anmelden und in den Warenkorb legen</a><?php endif;?>
</form>

<?php if($seller && !empty($offer['is_private']) && $offer['private_offer_status']==='pending'):?>
<form method="post" action="/konto/angebote/<?=$offer['offer_id']?>/ablehnen" class="decline-box offer-decline-box"><?=Csrf::field()?>
<label>Ablehnungsgrund<textarea name="reason" rows="3" required placeholder="Warum möchtest du dieses Privatangebot ablehnen?"></textarea></label>
<button class="btn wide" type="submit">Privatangebot ablehnen</button>
</form>
<?php endif;?>
</article>
</main>