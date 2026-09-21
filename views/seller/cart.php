<main class="section">
<div class="seller-hero">
  <div>
    <span class="eyebrow">Warenkorb</span>
    <h1>Mehrere Angebote, ein gemeinsamer Auftrag.</h1>
    <p>Prüfe deine Auswahl. Beim Checkout werden alle Positionen zu einer Auftragsnummer zusammengeführt.</p>
  </div>
  <a class="btn ghost" href="/angebote">Weitere Angebote hinzufügen</a>
</div>

<?php if(!$items):?>
<div class="empty seller-section-card">
  <h2>Dein Warenkorb ist leer.</h2>
  <p>Lege ein oder mehrere passende Angebote hinein und schließe sie anschließend gemeinsam ab.</p>
  <a class="btn primary" href="/angebote">Angebote ansehen</a>
</div>
<?php else:?>

<div class="cart-layout">
  <section class="cart-items">
    <?php foreach($items as $index=>$item):?>
      <article class="card cart-item-card <?=$item['invalid_reason']?'invalid':''?>">
        <div class="cart-item-head">
          <div>
            <span class="eyebrow">Angebot <?=($index+1)?></span>
            <h2><?=App\Core\View::e($item['title'])?></h2>
            <p class="muted"><?=App\Core\View::e($item['category_name'])?><?php if($item['duration_value']):?> · <?= (int)$item['duration_value'] ?> <?=App\Core\View::e($item['duration_unit']??'')?><?php endif;?></p>
          </div>
          <strong class="cart-item-total"><?=number_format((float)$item['total'],2,',','.')?> €</strong>
        </div>

        <?php if($item['invalid_reason']):?>
          <div class="alert danger"><strong>Diese Position muss geprüft werden.</strong><br><?=App\Core\View::e($item['invalid_reason'])?></div>
        <?php endif;?>

        <div class="cart-price-lines">
          <div><span>Grundvergütung</span><strong><?=number_format((float)$item['compensation'],2,',','.')?> €</strong></div>
          <?php foreach($item['options'] as $option):?>
            <div><span>Option: <?=App\Core\View::e($option['name'])?></span><strong>+ <?=number_format((float)$option['price'],2,',','.')?> €</strong></div>
          <?php endforeach;?>
        </div>

        <div class="row-actions cart-item-actions">
          <a class="btn ghost" href="/angebote/<?=$item['offer_id']?>">Auswahl ändern</a>
          <form method="post" action="/konto/warenkorb/position/<?=$item['id']?>/entfernen">
            <?=App\Core\Csrf::field()?>
            <button class="btn ghost" type="submit">Entfernen</button>
          </form>
        </div>
      </article>
    <?php endforeach;?>
  </section>

  <aside class="card cart-checkout-card">
    <span class="eyebrow">Checkout</span>
    <h2>Dein Sammelauftrag</h2>
    <div class="cart-summary-row"><span>Angebote</span><strong><?=count($items)?></strong></div>
    <div class="cart-summary-row total"><span>Gesamtvergütung</span><strong><?=number_format((float)$cartTotal,2,',','.')?> €</strong></div>

    <div class="notice">
      <strong>Nach dem Checkout entsteht nur ein Auftrag.</strong><br>
      Alle enthaltenen Angebote bleiben intern getrennt nachvollziehbar, teilen sich aber eine Auftragsnummer, einen Chat und einen gemeinsamen Wallet-Vorgang.
    </div>

    <?php if(!$hasInvalid):?>
    <form method="post" action="/konto/warenkorb/checkout" class="cart-checkout-form">
      <?=App\Core\Csrf::field()?>

      <div class="cart-confirmations">
        <label class="check"><input type="checkbox" name="adult_confirmation" value="1" required><span><b>Volljährig</b><small>Ich bin mindestens 18 Jahre alt und nehme den Auftrag persönlich an.</small></span></label>
        <label class="check"><input type="checkbox" name="own_goods_confirmation" value="1" required><span><b>Eigene Artikel & Inhalte</b><small>Alle Artikel und Inhalte stammen von mir beziehungsweise werden von mir selbst erstellt.</small></span></label>
        <label class="check"><input type="checkbox" name="no_third_parties_confirmation" value="1" required><span><b>Keine unzulässigen Dritten</b><small>Es sind keine Minderjährigen oder Personen ohne erforderliche Einwilligung beteiligt.</small></span></label>
        <?php if($hasDigital):?><label class="check"><input type="checkbox" name="rights_acceptance" value="1" required><span><b>Digitale Rechtevereinbarung</b><small>Ich bestätige die für die enthaltenen digitalen Bestandteile erforderliche Rechtevereinbarung.</small></span></label><?php endif;?>
        <label class="check"><input type="checkbox" name="summary_confirmation" value="1" required><span><b>Warenkorb geprüft</b><small>Ich habe alle Angebote, Optionen, Vergütungen und Anforderungen geprüft.</small></span></label>
      </div>

      <button class="btn primary wide cart-checkout-button" type="submit"><?=count($items)>1?'Als einen Auftrag abschließen':'Auftrag abschließen'?></button>
    </form>
    <?php else:?>
      <div class="alert danger">Der Checkout ist gesperrt, bis alle ungültigen Positionen korrigiert oder entfernt wurden.</div>
    <?php endif;?>
  </aside>
</div>
<?php endif;?>
</main>