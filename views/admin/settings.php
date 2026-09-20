<main class="section">
<div class="admin-page-intro"><div><span class="eyebrow">System</span><h1>Globale Einstellungen</h1><p>Technische und kaufmännische Grundeinstellungen der Plattform.</p></div></div>

<section class="card admin-form settings-section"><h2>Cronjob für ALL-INKL</h2>
<p class="muted">Bei ALL-INKL kannst du einen URL-Cronjob anlegen. Verwende dafür genau diese geheime URL. Sie startet die Fristen-, Erinnerungs- und Bereinigungslogik.</p>
<?php if(!empty($cronUrl)):?>
<div class="cron-url-box"><input id="cron-url" type="text" readonly value="<?=App\Core\View::e($cronUrl)?>"><button class="btn" type="button" data-copy-target="#cron-url">URL kopieren</button></div>
<p class="field-help">Empfehlung: Cronjob alle 5 Minuten aufrufen. Die URL enthält einen geheimen Schlüssel und darf nicht öffentlich geteilt werden.</p>
<form method="post" action="/admin/einstellungen/cron-neu"><?=App\Core\Csrf::field()?><button class="btn ghost" data-confirm="Wirklich eine neue Cron-URL erzeugen? Die bisherige URL funktioniert danach nicht mehr.">Neue geheime Cron-URL erzeugen</button></form>
<?php else:?><div class="alert danger">Die Cron-URL kann noch nicht angezeigt werden. Prüfe die öffentliche URL in der Installation bzw. Laufzeitkonfiguration und führe offene Migrationen aus.</div><?php endif;?>
</section>

<section class="card admin-form settings-section"><h2>Plattform & Kontakt</h2><form method="post" action="/admin/einstellungen" class="form-grid"><?=App\Core\Csrf::field()?>
<label>Plattformname<input name="platform_name" value="<?=App\Core\View::e($settings['platform_name']??'FetischShop')?>"></label>
<label>Kontakt-E-Mail<input type="email" name="contact_email" value="<?=App\Core\View::e($settings['contact_email']??'')?>"></label>

<h3 class="full">Auszahlungen</h3>
<label>Mindest-Auszahlung (€)<input type="number" step="0.01" name="payout_minimum" value="<?=App\Core\View::e($settings['payout_minimum']??'10.00')?>"></label>
<label>Auszahlungstage<input name="payout_days" value="<?=App\Core\View::e($settings['payout_days']??'Montag,Donnerstag')?>"><small class="field-help">Zum Beispiel: Montag, Donnerstag</small></label>
<label>Banküberweisung<select name="payout_bank_enabled"><option value="1" <?=($settings['payout_bank_enabled']??'1')==='1'?'selected':''?>>Aktiv</option><option value="0" <?=($settings['payout_bank_enabled']??'1')==='0'?'selected':''?>>Deaktiviert</option></select></label>
<label>PayPal<select name="payout_paypal_enabled"><option value="1" <?=($settings['payout_paypal_enabled']??'1')==='1'?'selected':''?>>Aktiv</option><option value="0" <?=($settings['payout_paypal_enabled']??'1')==='0'?'selected':''?>>Deaktiviert</option></select></label>
<label>Bank-Gebühr<select name="payout_bank_fee_type"><?php foreach(['none'=>'Keine Gebühr','fixed'=>'Fester Betrag','percent'=>'Prozent vom Betrag'] as $k=>$v):?><option value="<?=$k?>" <?=($settings['payout_bank_fee_type']??'none')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label>Bank-Gebühr Wert<input type="number" step="0.01" name="payout_bank_fee_value" value="<?=App\Core\View::e($settings['payout_bank_fee_value']??'0')?>"></label>
<label>PayPal-Gebühr<select name="payout_paypal_fee_type"><?php foreach(['none'=>'Keine Gebühr','fixed'=>'Fester Betrag','percent'=>'Prozent vom Betrag'] as $k=>$v):?><option value="<?=$k?>" <?=($settings['payout_paypal_fee_type']??'none')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label>PayPal-Gebühr Wert<input type="number" step="0.01" name="payout_paypal_fee_value" value="<?=App\Core\View::e($settings['payout_paypal_fee_value']??'0')?>"></label>

<h3 class="full">Dateien & Fristen</h3>
<label>Bild-Upload max. MB<input type="number" min="1" name="upload_image_mb" value="<?=App\Core\View::e($settings['upload_image_mb']??'12')?>"></label>
<label>Digital-Upload max. MB<input type="number" min="1" name="upload_digital_mb" value="<?=App\Core\View::e($settings['upload_digital_mb']??'150')?>"></label>
<label>„Bald fällig“ ab Minuten<input type="number" min="1" name="escalation_soon_minutes" value="<?=App\Core\View::e($settings['escalation_soon_minutes']??'60')?>"></label>
<label>„Kritisch“ ab Minuten<input type="number" min="1" name="escalation_critical_minutes" value="<?=App\Core\View::e($settings['escalation_critical_minutes']??'15')?>"></label>
<label>Erinnerung 60 Minuten vorher<select name="evidence_reminder_60"><option value="1" <?=($settings['evidence_reminder_60']??'1')==='1'?'selected':''?>>Aktiv</option><option value="0" <?=($settings['evidence_reminder_60']??'1')==='0'?'selected':''?>>Deaktiviert</option></select></label>
<label>Erinnerung 15 Minuten vorher<select name="evidence_reminder_15"><option value="1" <?=($settings['evidence_reminder_15']??'1')==='1'?'selected':''?>>Aktiv</option><option value="0" <?=($settings['evidence_reminder_15']??'1')==='0'?'selected':''?>>Deaktiviert</option></select></label>
<button class="btn primary full">Einstellungen speichern</button></form></section>

<section class="card admin-form settings-section"><h2>Technischen Plattformausfall dokumentieren</h2><p class="muted">Aktive Fristen werden automatisch um die dokumentierte Ausfalldauer verschoben.</p><form method="post" action="/admin/einstellungen/ausfall" class="form-grid"><?=App\Core\Csrf::field()?><label>Beginn<input type="datetime-local" name="starts_at" required></label><label>Ende<input type="datetime-local" name="ends_at" required></label><label class="full">Grund<textarea name="reason" rows="2" placeholder="Zum Beispiel: Wartungsarbeiten beim Hostinganbieter"></textarea></label><button class="btn full">Ausfall dokumentieren</button></form></section>

<section><h2>Ausfallhistorie</h2><div class="worklist"><?php foreach($outages as $o):?><div class="work-row"><div><b><?=date('d.m.Y H:i',strtotime($o['starts_at']))?> – <?=date('d.m.Y H:i',strtotime($o['ends_at']))?></b><span><?=App\Core\View::e($o['reason']??'Systemausfall')?></span></div></div><?php endforeach;?><?php if(!$outages):?><div class="empty">Keine dokumentierten Ausfälle.</div><?php endif;?></div></section>
</main>