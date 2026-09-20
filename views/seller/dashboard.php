<?php
$statusLabels=['precheck'=>'Vorabkontrolle','running'=>'In Durchführung','shipping'=>'Versand','digital_review'=>'Digitale Prüfung','reviewing'=>'Abschlussprüfung','completed'=>'Auszahlbar','rejected'=>'Abgelehnt','archived'=>'Archiviert','cancelled'=>'Storniert','paid'=>'Ausgezahlt'];
$phaseProgress=['preparation'=>15,'execution'=>45,'shipping'=>65,'review'=>82,'payout'=>94,'archive'=>100];
?>
<main class="section">
<div class="seller-hero"><div><span class="eyebrow">Dein Überblick</span><h1>Hallo <?=App\Core\View::e($seller['first_name'])?></h1><p>Offene Schritte, laufende Aufträge und dein aktueller Wallet-Stand auf einen Blick.</p></div><a class="btn primary" href="/angebote">Angebote ansehen</a></div>

<?php if(!$seller['email_verified_at']):?><div class="notice">Deine E-Mail-Adresse ist noch nicht bestätigt. Ohne Bestätigung kannst du keine neuen Aufträge annehmen. <a href="/konto/email-bestaetigen"><strong>Jetzt bestätigen →</strong></a></div><?php endif;?>

<div class="seller-kpi-grid">
<a class="seller-kpi" href="/konto/auftraege"><span>Aktive Aufträge</span><strong><?=$activeOrders?></strong><small>noch nicht abgeschlossen</small></a>
<a class="seller-kpi" href="/konto/wallet"><span>Verfügbar</span><strong><?=number_format((float)($wallet['balance_available']??0),2,',','.')?> €</strong><small>bereit zur Auszahlung</small></a>
<a class="seller-kpi" href="/konto/wallet"><span>Vorgemerkt</span><strong><?=number_format((float)($wallet['balance_reserved']??0),2,',','.')?> €</strong><small>noch nicht freigegeben</small></a>
<a class="seller-kpi" href="/konto/benachrichtigungen"><span>Ungelesen</span><strong><?=$unread?></strong><small>Benachrichtigungen</small></a>
</div>

<div class="seller-home-grid">
<section class="card">
<div class="section-head compact"><div><h2>Jetzt zu erledigen</h2><p class="muted">Das solltest du als Nächstes erledigen.</p></div></div>
<div class="seller-action-list">
<?php foreach($actions as $a): $cls=$a['priority']===0?'danger':($a['priority']===1?'warn':'');?>
<a class="seller-action" href="<?=App\Core\View::e($a['url'])?>"><div><b><?=App\Core\View::e($a['title'])?></b><span><?=App\Core\View::e($a['text'])?><?php if($a['due']):?> · fällig <?=date('d.m.Y H:i',strtotime($a['due']))?><?php endif;?></span></div><span class="seller-status <?=$cls?>"><?=$a['priority']===0?'Jetzt':($a['priority']===1?'Wichtig':'Offen')?></span></a>
<?php endforeach;?>
<?php if(!$actions):?><div class="empty">Aktuell ist nichts dringend zu erledigen.</div><?php endif;?>
</div>
</section>

<aside class="card">
<div class="section-head compact"><div><h2>Private Angebote</h2><p class="muted">Individuelle Angebote, die nur deinem Konto zugeordnet sind.</p></div></div>
<div class="seller-action-list"><?php foreach($privateOffers as $po):?><a class="seller-action" href="/angebote/<?=$po['id']?>"><div><b><?=App\Core\View::e($po['title'])?></b><span><?=App\Core\View::e($po['category_name'])?><?php if($po['acceptance_deadline']):?> · bis <?=date('d.m.Y H:i',strtotime($po['acceptance_deadline']))?><?php endif;?></span></div><strong><?=number_format((float)$po['compensation'],2,',','.')?> €</strong></a><?php endforeach;?><?php if(!$privateOffers):?><div class="empty">Keine offenen Privatangebote.</div><?php endif;?></div>
</aside>
</div>

<section class="seller-section-card"><div class="section-head compact"><div><h2>Aktuelle Aufträge</h2><p class="muted">Direkter Zugriff auf deine laufenden Aufträge.</p></div><a class="btn ghost" href="/konto/auftraege">Alle Aufträge</a></div>
<div class="seller-action-list"><?php foreach($orders as $o): $progress=$phaseProgress[$o['phase']]??30;?>
<a class="seller-order-card" href="/konto/auftraege/<?=$o['id']?>"><div class="top"><div><b>#<?=App\Core\View::e($o['order_number'])?> · <?=App\Core\View::e($o['title'])?></b><div class="meta"><?=App\Core\View::e($statusLabels[$o['status']]??$o['status'])?><?=$o['component_count']>1?' · Kombi-Auftrag mit '.$o['component_count'].' Bestandteilen':''?></div></div><strong><?=number_format((float)$o['current_total'],2,',','.')?> €</strong></div><div class="seller-order-progress"><span style="width:<?=$progress?>%"></span></div></a>
<?php endforeach;?><?php if(!$orders):?><div class="empty">Noch keine Aufträge vorhanden. Unter „Angebote“ findest du verfügbare Aufträge.</div><?php endif;?></div>
</section>
</main>