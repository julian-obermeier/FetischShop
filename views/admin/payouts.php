<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">Finanzen</span><h1>Auszahlungsanträge</h1><p>Prüfe beantragte Auszahlungen, Zahlungsmethode, Gebühren und zugeordnete Aufträge.</p></div><a class="btn ghost" href="/admin">Zur Übersicht</a></div>
<div class="worklist">
<?php foreach($payouts as $p): $snap=json_decode($p['method_snapshot']??'{}',true)?:[]; ?>
<div class="card">
<div class="section-head"><div><b><?=App\Core\View::e($p['first_name'].' '.$p['last_name'])?></b><span><?=App\Core\View::e($p['email'])?> · <?=date('d.m.Y H:i',strtotime($p['requested_at']))?></span></div><strong><?=number_format((float)$p['net_amount'],2,',','.')?> € Auszahlungsbetrag</strong></div>
<p>Angefordert: <?=number_format((float)$p['requested_amount'],2,',','.')?> € · Gebühr: <?=number_format((float)$p['fee_amount'],2,',','.')?> € · Status: <b><?=App\Core\View::e($p['status'])?></b></p>
<p><?php if(($snap['method_type']??'')==='bank'):?>Bank · <?=App\Core\View::e($snap['account_holder']??'')?> · <?=App\Core\View::e($snap['iban']??'')?><?php else:?>PayPal · <?=App\Core\View::e($snap['paypal_identifier']??'')?><?php endif;?></p>
<?php if(!empty($payoutAllocations[$p['id']])):?><div class="worklist"><?php foreach($payoutAllocations[$p['id']] as $a):?><div class="work-row"><div><b>Auftrag #<?=App\Core\View::e($a['order_number'])?></b><span>durch diese Auszahlung abgedeckt</span></div><strong><?=number_format((float)$a['amount'],2,',','.')?> €</strong></div><?php endforeach;?></div><?php endif;?>
<?php if(!in_array($p['status'],['paid','withdrawn','rejected'],true)):?><form method="post" action="/admin/auszahlungen/<?=$p['id']?>"><?=App\Core\Csrf::field()?><select name="status"><option value="in_review">In Prüfung</option><option value="approved">Freigegeben</option><option value="paid">Ausgezahlt</option><option value="rejected">Abgelehnt</option></select><button class="btn">Auszahlungsstatus speichern</button></form><?php endif;?>
</div>
<?php endforeach;?><?php if(!$payouts):?><div class="empty">Aktuell liegen keine Auszahlungsanträge vor.</div><?php endif;?>
</div></main>