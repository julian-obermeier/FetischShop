<?php
$payoutStatus=['requested'=>'Beantragt','in_review'=>'In Prüfung','approved'=>'Freigegeben','paid'=>'Ausgezahlt','rejected'=>'Abgelehnt','withdrawn'=>'Zurückgezogen'];
$entryLabels=['order_reservation'=>'Auftrag vorgemerkt','order_review_hold'=>'Auftrag in Prüfung','order_release'=>'Vergütung freigegeben','option_change'=>'Option geändert','paid_extra_day'=>'Bezahlter Zusatztag','payout'=>'Auszahlung','restart_cancel'=>'Neustart – alte Reservierung aufgehoben','restart_reservation'=>'Neustart – neu vorgemerkt'];
$entryStatus=['reserved'=>'Vorgemerkt','in_review'=>'In Prüfung','available'=>'Verfügbar','paid'=>'Ausgezahlt','cancelled'=>'Storniert'];
?>
<main class="section">
<div class="seller-hero"><div><span class="eyebrow">Wallet</span><h1>Guthaben & Auszahlungen</h1><p>Sieh sofort, welcher Betrag noch gebunden ist, geprüft wird oder bereits ausgezahlt werden kann.</p></div></div>

<div class="seller-wallet-grid">
<div class="seller-wallet-box"><span>Vorgemerkt</span><strong><?=number_format((float)($wallet['balance_reserved']??0),2,',','.')?> €</strong><small class="muted">noch gebunden</small></div>
<div class="seller-wallet-box"><span>In Prüfung</span><strong><?=number_format((float)($wallet['balance_in_review']??0),2,',','.')?> €</strong><small class="muted">wird noch geprüft</small></div>
<div class="seller-wallet-box available"><span>Verfügbar</span><strong><?=number_format((float)($wallet['balance_available']??0),2,',','.')?> €</strong><small class="muted">sofort auszahlbar</small></div>
</div>

<div class="seller-home-grid">
<section class="card admin-form"><h2>Auszahlungsmethode</h2><p class="muted">Hinterlege eine Bankverbindung oder PayPal-Adresse. Bei einer neuen Angabe desselben Typs wird die bisherige aktive Methode ersetzt.</p>
<?php if(($payoutSettings['payout_bank_enabled']??'1')==='1'):?>
<form method="post" action="/konto/wallet/methode" class="form-grid"><?=App\Core\Csrf::field()?><input type="hidden" name="method_type" value="bank"><label class="full">Kontoinhaber<input name="account_holder" required></label><label class="full">IBAN<input name="iban" autocomplete="off" required></label><label class="full">BIC (optional)<input name="bic" autocomplete="off"></label><button class="btn full">Bankverbindung speichern</button></form>
<?php endif;?>
<?php if(($payoutSettings['payout_paypal_enabled']??'1')==='1'):?>
<form method="post" action="/konto/wallet/methode" class="form-grid"><?=App\Core\Csrf::field()?><input type="hidden" name="method_type" value="paypal"><label class="full">PayPal-E-Mail<input name="paypal_identifier" type="email" required></label><button class="btn full">PayPal speichern</button></form>
<?php endif;?>
<?php if($methods):?><h3>Aktive Auszahlungsmethoden</h3><div class="seller-action-list"><?php foreach($methods as $m): if(!$m['is_active'])continue;?><div class="seller-action"><div><b><?=App\Core\View::e($m['method_type']==='bank'?'Banküberweisung':'PayPal')?></b><span><?php if($m['method_type']==='bank'):?><?=App\Core\View::e($m['account_holder'])?> · IBAN •••• <?=App\Core\View::e(substr(preg_replace('/\s+/','',(string)$m['iban']),-4))?><?php else:?><?=App\Core\View::e($m['paypal_identifier'])?><?php endif;?></span></div><span class="seller-status ok">Aktiv</span></div><?php endforeach;?></div><?php endif;?>
</section>

<section class="card admin-form"><h2>Auszahlung beantragen</h2><p class="muted">Verfügbares Guthaben kannst du ab folgendem Mindestbetrag auszahlen lassen: <?=number_format((float)($payoutSettings['payout_minimum']??10),2,',','.')?> € · Bearbeitung: <?=App\Core\View::e($payoutSettings['payout_days']??'nach Einstellung')?></p>
<?php $active=array_values(array_filter($methods??[],fn($x)=>(int)$x['is_active']===1));?>
<?php if((float)($wallet['balance_available']??0)<(float)($payoutSettings['payout_minimum']??10)):?><div class="notice">Aktuell ist noch nicht genug verfügbares Guthaben für eine Auszahlung vorhanden.</div>
<?php elseif($active):?><form method="post" action="/konto/wallet/auszahlung" class="form-grid"><?=App\Core\Csrf::field()?><label class="full">Betrag<input name="amount" type="number" step="0.01" min="<?=App\Core\View::e($payoutSettings['payout_minimum']??10)?>" max="<?=App\Core\View::e($wallet['balance_available']??0)?>" required></label><label class="full">Auszahlung über<select name="payout_method_id"><?php foreach($active as $m):?><option value="<?=$m['id']?>"><?=App\Core\View::e($m['method_type']==='bank'?'Banküberweisung':'PayPal')?></option><?php endforeach;?></select></label><button class="btn primary full">Auszahlung beantragen</button></form>
<?php else:?><div class="notice">Bitte zuerst eine Auszahlungsmethode hinterlegen.</div><?php endif;?>
</section>
</div>

<section class="seller-section-card"><div class="section-head compact"><div><h2>Auszahlungsverlauf</h2><p class="muted">Alle bisherigen Auszahlungsanträge mit Status und Zuordnung.</p></div></div><div class="seller-action-list">
<?php foreach($requests??[] as $p):?><div class="seller-order-card"><div class="top"><div><b><?=number_format((float)$p['net_amount'],2,',','.')?> € Auszahlung</b><div class="meta"><?=App\Core\View::e($payoutStatus[$p['status']]??$p['status'])?> · beantragt <?=date('d.m.Y H:i',strtotime($p['requested_at']))?><?php if((float)$p['fee_amount']>0):?> · Gebühr <?=number_format((float)$p['fee_amount'],2,',','.')?> €<?php endif;?></div></div><?php if($p['status']==='requested'):?><form method="post" action="/konto/wallet/auszahlung/<?=$p['id']?>/zurueckziehen"><?=App\Core\Csrf::field()?><button class="btn ghost">Zurückziehen</button></form><?php else:?><span class="seller-status <?=($p['status']==='paid'?'ok':($p['status']==='rejected'?'danger':'warn'))?>"><?=App\Core\View::e($payoutStatus[$p['status']]??$p['status'])?></span><?php endif;?></div>
<?php if(!empty($payoutAllocations[$p['id']])):?><div class="worklist"><?php foreach($payoutAllocations[$p['id']] as $a):?><div class="work-row"><div><b>Auftrag #<?=App\Core\View::e($a['order_number'])?></b><span>zu dieser Auszahlung</span></div><strong><?=number_format((float)$a['amount'],2,',','.')?> €</strong></div><?php endforeach;?></div><?php endif;?></div><?php endforeach;?>
<?php if(empty($requests)):?><div class="empty">Noch keine Auszahlungen beantragt.</div><?php endif;?></div></section>

<section class="seller-section-card"><details><summary><strong>Buchungsverlauf anzeigen</strong></summary><div class="worklist" style="margin-top:14px"><?php foreach($entries as $e):?><div class="work-row"><div><b><?=App\Core\View::e($entryLabels[$e['entry_type']]??$e['entry_type'])?></b><span><?=App\Core\View::e($entryStatus[$e['status']]??$e['status'])?> · <?=date('d.m.Y H:i',strtotime($e['created_at']))?></span></div><strong><?=((float)$e['amount']>0?'+ ':'')?><?=number_format((float)$e['amount'],2,',','.')?> €</strong></div><?php endforeach;?><?php if(!$entries):?><div class="empty">Noch keine Wallet-Buchungen.</div><?php endif;?></div></details></section>
</main>