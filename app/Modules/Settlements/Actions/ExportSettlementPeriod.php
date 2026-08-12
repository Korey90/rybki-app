<?php

namespace App\Modules\Settlements\Actions;

use App\Modules\Settlements\Enums\SettlementItemType;
use App\Modules\Settlements\Models\SettlementItem;
use App\Modules\Settlements\Models\SettlementPeriod;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSettlementPeriod
{
    public function csv(SettlementPeriod $settlementPeriod): StreamedResponse
    {
        $settlementPeriod->loadMissing(['organization', 'providerAccount', 'payout', 'items.booking', 'items.payment', 'items.refund']);

        return response()->streamDownload(function () use ($settlementPeriod): void {
            $handle = fopen('php://output', 'w');
            assert(is_resource($handle));

            fputcsv($handle, ['Rozliczenie', $settlementPeriod->uuid], ';');
            fputcsv($handle, ['Organizacja', $settlementPeriod->organization->name], ';');
            fputcsv($handle, ['Okres', $settlementPeriod->period_start->toDateString().' - '.$settlementPeriod->period_end->toDateString()], ';');
            fputcsv($handle, ['Brutto', $settlementPeriod->gross_amount, $settlementPeriod->currency], ';');
            fputcsv($handle, ['Prowizja', $settlementPeriod->platform_fee_amount, $settlementPeriod->currency], ';');
            fputcsv($handle, ['Zwroty', $settlementPeriod->refund_amount, $settlementPeriod->currency], ';');
            fputcsv($handle, ['Do wypłaty', $settlementPeriod->net_payout_amount, $settlementPeriod->currency], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, [
                'Typ',
                'Data',
                'Booking',
                'Payment',
                'Refund',
                'Brutto',
                'Prowizja',
                'Zwrot',
                'Operator',
                'Waluta',
            ], ';');

            $settlementPeriod->items
                ->sortBy('occurred_at')
                ->each(function (SettlementItem $item) use ($handle): void {
                    fputcsv($handle, [
                        $item->type->value,
                        $item->occurred_at->toDateTimeString(),
                        $item->booking?->booking_number,
                        $item->payment?->provider_payment_id,
                        $item->refund?->provider_refund_id,
                        $item->gross_amount,
                        $item->platform_fee_amount,
                        $item->refund_amount,
                        $item->operator_amount,
                        $item->currency,
                    ], ';');
                });

            fclose($handle);
        }, $this->filename($settlementPeriod, 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(SettlementPeriod $settlementPeriod): Response
    {
        $settlementPeriod->loadMissing(['organization', 'providerAccount', 'payout', 'items.booking', 'items.payment', 'items.refund']);

        $lines = [
            'Rozliczenie '.$settlementPeriod->uuid,
            'Organizacja: '.$settlementPeriod->organization->name,
            'Okres: '.$settlementPeriod->period_start->toDateString().' - '.$settlementPeriod->period_end->toDateString(),
            'Status: '.$settlementPeriod->status->value,
            'Brutto: '.$this->money($settlementPeriod->gross_amount, $settlementPeriod->currency),
            'Prowizja: '.$this->money($settlementPeriod->platform_fee_amount, $settlementPeriod->currency),
            'Zwroty: '.$this->money($settlementPeriod->refund_amount, $settlementPeriod->currency),
            'Do wyplaty: '.$this->money($settlementPeriod->net_payout_amount, $settlementPeriod->currency),
            'Provider payout: '.($settlementPeriod->payout()->value('provider_payout_id') ?: '-'),
            '',
            'Pozycje:',
        ];

        $settlementPeriod->items
            ->sortBy('occurred_at')
            ->take(36)
            ->each(function (SettlementItem $item) use (&$lines): void {
                $direction = $item->type === SettlementItemType::Refund ? 'zwrot' : 'platnosc';
                $bookingNumber = $item->booking?->booking_number;

                $lines[] = sprintf(
                    '%s | %s | %s | operator %s',
                    $item->occurred_at->toDateString(),
                    $direction,
                    $bookingNumber ?: '-',
                    $this->money($item->operator_amount, $item->currency),
                );
            });

        return response($this->buildPdf($lines), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($settlementPeriod, 'pdf').'"',
        ]);
    }

    private function money(int $amount, string $currency): string
    {
        return number_format($amount / 100, 2, ',', ' ').' '.$currency;
    }

    private function filename(SettlementPeriod $settlementPeriod, string $extension): string
    {
        $organization = Str::slug($settlementPeriod->organization->name);

        return "rozliczenie-{$organization}-{$settlementPeriod->period_start->toDateString()}-{$settlementPeriod->period_end->toDateString()}.{$extension}";
    }

    /**
     * @param  list<string>  $lines
     */
    private function buildPdf(array $lines): string
    {
        $content = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";

        foreach ($lines as $line) {
            $text = Str::limit(Str::ascii($line), 105, '');
            $content .= '('.$this->escapePdfText($text).") Tj\nT*\n";
        }

        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }
}
