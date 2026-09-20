<main class="auth-shell"><section class="card auth-card"><span class="eyebrow">Einrichtung</span><h1>FetischShop installieren</h1>
<?php if(!empty($error)):?><div class="alert danger"><?=App\Core\View::e($error)?></div><?php endif;?>
<?php if(!empty($checks)):?><div class="check-list"><?php foreach($checks as $label=>$ok):?><div><span><?= $ok?'✓':'✕' ?></span> <?=App\Core\View::e($label)?></div><?php endforeach;?></div><?php endif;?>
<form method="post" class="form-grid"><?=App\Core\Csrf::field()?>
<label>Datenbankhost<input name="db_host" value="localhost" required></label><label>Port<input name="db_port" type="number" value="3306" required></label>
<label>Datenbankname<input name="db_name" required></label><label>Datenbankbenutzer<input name="db_user" required></label>
<label class="full">Datenbankpasswort<input name="db_password" type="password"></label>
<label class="full">Admin-E-Mail<input name="admin_email" type="email" required></label><label class="full">Admin-Passwort<input name="admin_password" type="password" minlength="12" required></label>
<button class="btn primary full" type="submit">Installation starten</button></form></section></main>