<main>
<section class="hero public-hero">
  <div class="public-hero-copy">
    <span class="eyebrow">Direkter Ankauf · klar geregelt · 18+</span>
    <h1>Du wählst den Auftrag.<br>Wir kümmern uns um den Ablauf.</h1>
    <p>FetischShop ist keine offene Verkaufsplattform. Du nimmst konkrete Ankaufangebote an, erfüllst die vereinbarten Schritte und erhältst nach erfolgreichem Abschluss die festgelegte Vergütung.</p>
    <div class="actions">
      <a class="btn primary" href="/angebote">Angebote entdecken</a>
      <a class="btn ghost" href="/registrieren">Verkäuferinnen-Konto erstellen</a>
    </div>
    <div class="public-trust-row">
      <span>✓ keine öffentlichen Profile</span>
      <span>✓ keine fremden Käuferkonten</span>
      <span>✓ klare Auftragsregeln</span>
    </div>
  </div>
  <aside class="hero-card public-process-card">
    <span class="eyebrow">In 5 Schritten</span>
    <ol>
      <li><b>01</b><span>Angebot auswählen</span></li>
      <li><b>02</b><span>Artikel & Startkontrolle</span></li>
      <li><b>03</b><span>Auftrag durchführen</span></li>
      <li><b>04</b><span>Versand oder digitale Abgabe</span></li>
      <li><b>05</b><span>Prüfung & Auszahlung</span></li>
    </ol>
  </aside>
</section>

<section class="section">
  <div class="section-head">
    <div><span class="eyebrow">Aktuell verfügbar</span><h2>Offene Ankaufangebote</h2><p class="muted">Vergütung, Dauer und Anforderungen siehst du vor der Annahme vollständig.</p></div>
    <a href="/angebote">Alle Angebote ansehen →</a>
  </div>
  <div class="offer-grid">
    <?php foreach($offers as $o):?>
      <a class="offer-card" href="/angebote/<?= (int)$o['id'] ?>">
        <span class="pill"><?=App\Core\View::e($o['category_name'])?></span>
        <h3><?=App\Core\View::e($o['title'])?></h3>
        <div class="offer-card-bottom">
          <div class="price"><?=number_format((float)$o['compensation'],2,',','.')?> €</div>
          <small><?=App\Core\View::e(trim(($o['duration_value']??'').' '.($o['duration_unit']??'')))?></small>
        </div>
      </a>
    <?php endforeach;?>
    <?php if(!$offers):?><div class="empty">Aktuell sind keine öffentlichen Angebote verfügbar.</div><?php endif;?>
  </div>
</section>

<section class="section public-feature-grid">
  <article><span>01</span><h3>Klare Bedingungen</h3><p>Du siehst vorab, was gefordert wird, welche Fristen gelten und wie hoch die Vergütung ist.</p></article>
  <article><span>02</span><h3>Alles an einem Ort</h3><p>Nachweise, Aufgaben, Nachrichten, Versand, digitale Abgaben und Auszahlungen laufen über deinen persönlichen Bereich.</p></article>
  <article><span>03</span><h3>Nachvollziehbare Entscheidungen</h3><p>Prüfungen, Änderungen und Abschlussentscheidungen bleiben im Auftrag dokumentiert.</p></article>
</section>
</main>