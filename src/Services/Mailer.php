<?php
namespace App\Services;

final class Mailer
{
    public function __construct(private array $config) {}

    public function send(string $to, string $subject, string $html): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->config['mail_from_name'] . ' <' . $this->config['mail_from'] . '>',
            'X-Mailer: FetischShop',
        ];

        return mail(
            $to,
            '=?UTF-8?B?' . base64_encode($subject) . '?=',
            $this->layout($html),
            implode("\r\n", $headers)
        );
    }

    public function sendOrderConfirmation(string $to, string $name, array $order, array $components, array $options): bool
    {
        $sourceTitles=array_values(array_unique(array_filter(array_map(static fn(array $component)=>(string)($component['source_offer_title']??''),$components))));
        $isCombined=count($sourceTitles)>1;

        $componentRows = '';
        foreach ($components as $component) {
            $componentRows .= '<tr><td style="padding:8px;border-bottom:1px solid #ddd">'
                . $this->e((string) $component['title'])
                . (!empty($component['source_offer_title']) ? '<br><span style="font-size:12px;color:#666">aus ' . $this->e((string)$component['source_offer_title']) . '</span>' : '')
                . '</td><td style="padding:8px;border-bottom:1px solid #ddd">'
                . $this->e((string) ($component['category_name'] ?? $component['component_type']))
                . '</td><td style="padding:8px;border-bottom:1px solid #ddd;text-align:right">'
                . number_format((float) $component['compensation'], 2, ',', '.') . ' €</td></tr>';
        }

        $optionRows = '';
        foreach ($options as $option) {
            $optionRows .= '<li>' . $this->e((string) $option['name']) . ' – '
                . number_format((float) $option['price'], 2, ',', '.') . ' €</li>';
        }

        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $link = $baseUrl !== '' ? $baseUrl . '/konto/auftraege/' . (int) $order['id'] : '';

        $html = '<h1>Auftrag #' . $this->e((string) $order['order_number']) . ' bestätigt</h1>'
            . '<p>Hallo ' . $this->e($name) . ',</p>'
            . '<p>' . ($isCombined ? 'deine ausgewählten Angebote wurden zu einem gemeinsamen Sammelauftrag zusammengeführt.' : 'dein Auftrag wurde verbindlich angelegt.') . ' Alle beim Checkout geltenden Angebotsdaten wurden unveränderlich gespeichert.</p>'
            . '<table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:8px">Bestandteil</th><th style="text-align:left;padding:8px">Kategorie/Typ</th><th style="text-align:right;padding:8px">Vergütung</th></tr></thead><tbody>'
            . $componentRows . '</tbody></table>'
            . ($optionRows !== '' ? '<h2>Ausgewählte Optionen</h2><ul>' . $optionRows . '</ul>' : '')
            . '<p><strong>Aktueller Gesamtbetrag: ' . number_format((float) $order['current_total'], 2, ',', '.') . ' €</strong></p>'
            . '<p>Status: ' . $this->e((string) $order['status']) . ' · Phase: ' . $this->e((string) $order['phase']) . '</p>'
            . ($link !== '' ? '<p><a href="' . $this->e($link) . '">Auftrag öffnen</a></p>' : '')
            . '<p>Alle Nachweise, Änderungen, Optionen, Fristen und späteren Entscheidungen werden im Auftrag protokolliert.</p>';

        return $this->send($to, 'Auftragsbestätigung #' . $order['order_number'], $html);
    }

    private function layout(string $content): string
    {
        $name = $this->e((string) ($this->config['name'] ?? 'FetischShop'));
        return '<!doctype html><html lang="de"><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;line-height:1.5;color:#111;background:#f5f5f5;margin:0;padding:24px">'
            . '<div style="max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:12px">'
            . '<div style="font-size:20px;font-weight:700;margin-bottom:24px">' . $name . '</div>'
            . $content
            . '<hr style="border:0;border-top:1px solid #ddd;margin:28px 0">'
            . '<p style="font-size:12px;color:#666">Automatisch erzeugte Systemnachricht.</p>'
            . '</div></body></html>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
