<main class="section">
<div class="section-head"><div><span class="eyebrow">Finanzen</span><h1>Wallet & Auszahlungen</h1></div></div>
<div class="dash-grid"><?php $boxes=['Vorgemerkt'=>$wallet['balance_reserved']??0,'In Prüfung'=>$wallet['balance_in_review']??0,'Verfügbar'=>$wallet['balance_available']??0];foreach($boxes as $label=>$value):?><div class="card dash-card"><span><?=App\Core\View::e($label)?></span><strong><?=number_format((float)$value,2,',','.')?> €</strong></div><?php endforeach;?></div>

<div class="grid-2">
<section class="card"><h2>Auszahlungsmethode</h2>
<?php if(($payoutSettings['payout_bank_enabled']??'1')==='1'):?>
<form method="post" action="/konto/wallet/methode"><?=App\Core\Csrf::field()?><input type="hidden" name="method_type" value="bank"><label>Kontoinhaber<input name="account_holder" required></label><label>IBAN<input name="iban" required></label><label>BIC (optional)<input name="bic"></label><button class="btn">Bankdaten speichern</button></form>
<?php endif;?>
<?php if(($payoutSettings['payout_paypal_enabled']??'1')==='1'):?>
<form method="post" action="/konto/wallet/methode"><?=App\Core\Csrf::field()?><input type="hidden" name="method_type" value="paypal"><label>PayPal-E-Mail<input name="paypal_identifier" type="email" required></label><button class="btn">PayPal speichern</button></form>
<?php endif;?>
<?php if($methods):?><h3>Aktive Daten</h3><?php foreach($methods as $m): if(!$m['is_active'])continue;?><p><b><?=App\Core\View::e($m['method_type']==='bank'?'Banküberweisung':'PayPal')?></b><br><?php if($m['method_type']==='bank'):?><?=App\Core\View::e($m['account_holder'])?> · <?=App\Core\View::e($m['iban'])?><?php else:?><?=App\Core\View::e($m['paypal_identifier'])?><?php endif;?></p><?php endforeach;?><?php endif;?>
</section>

<section class="card"><h2>Auszahlung beantragen</h2><p>Mindestbetrag: <?=number_format((float)($payoutSettings['payout_minimum']??10),2,',','.')?> € · Bearbeitungstage: <?=App\Core\View::e($payoutSettings['payout_days']??'nach Einstellung')?></p>
<?php $active=array_values(array_filter($methods??[],fn($x)=>(int)$x['is_active']===1)); if($active):?><form method="post" action="/konto/wallet/auszahlung"><?=App\Core\Csrf::field()?><label>Betrag<input name="amount" type="number" step="0.01" min="<?=App\Core\View::e($payoutSettings['payout_minimum']??10)?>" max="<?=App\Core\View::e($wallet['balance_available']??0)?>" required></label><label>Methode<select name="payout_method_id"><?php foreach($active as $m):?><option value="<?=$m['id']?>"><?=App\Core\View::e($m['method_type']==='bank'?'Banküberweisung':'PayPal')?></option><?php endforeach;?></select></label><button class="btn">Auszahlung beantragen</button></form><?php else:?><p>Bitte zuerst eine Auszahlungsmethode hinterlegen.</p><?php endif;?>
</section>
</div>

<h2>Auszahlungsanträge</h2><div class="worklist"><?php foreach($requests??[] as $p):?><div class="card"><div class="work-row"><div><b><?=number_format((float)$p['net_amount'],2,',','.')?> € netto</b><span><?=App\Core\View::e($p['status'])?> · <?=date('d.m.Y H:i',strtotime($p['requested_at']))?></span></div><?php if($p['status']==='requested'):?><form method="post" action="/konto/wallet/auszahlung/<?=$p['id']?>/zurueckziehen"><?=App\Core\Csrf::field()?><button class="btn ghost">Zurückziehen</button></form><?php endif;?></div><?php if(!empty($payoutAllocations[$p['id']])):?><div class="worklist"><?php foreach($payoutAllocations[$p['id']] as $a):?><div class="work-row"><div><b>Auftrag #<?=App\Core\View::e($a['order_number'])?></b><span>ausgezahlt</span></div><strong><?=number_format((float)$a['amount'],2,',','.')?> €</strong></div><?php endforeach;?></div><?php endif;?></div><?php endforeach;?><?php if(empty($requests)):?><div class="empty">Noch keine Auszahlungsanträge.</div><?php endif;?></div>

<h2>Buchungen</h2><div class="worklist"><?php foreach($entries as $e):?><div class="work-row"><div><b><?=App\Core\View::e($e['entry_type'])?></b><span><?=App\Core\View::e($e['status'])?> · <?=date('d.m.Y H:i',strtotime($e['created_at']))?></span></div><strong><?=number_format((float)$e['amount'],2,',','.')?> €</strong></div><?php endforeach;?><?php if(!$entries):?><div class="empty">Noch keine Wallet-Buchungen.</div><?php endif;?></div>
</main>