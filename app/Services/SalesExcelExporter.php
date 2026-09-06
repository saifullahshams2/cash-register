<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use ZipArchive;

class SalesExcelExporter
{
    /**
     * Generate an .xlsx file binary content.
     *
     * @param  array{companyName: string, fromDate: string, toDate: string, revenue: float, count: int, cashRevenue: float, knetRevenue: float, currency?: string, currencyDecimals?: int}  $meta
     * @return string Binary contents of the generated .xlsx archive
     */
    public function generate(Collection $orders, array $meta): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive;
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="Sales Report" sheetId="1" r:id="rId1"/>'
            .'</sheets>'
            .'</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 5. xl/styles.xml
        $decimals = isset($meta['currencyDecimals']) ? (int) $meta['currencyDecimals'] : 3;
        $numFormatCode = $decimals > 0 ? '#,##0.'.str_repeat('0', $decimals) : '#,##0';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1">'
            .'<numFmt numFmtId="164" formatCode="'.$numFormatCode.'"/>'
            .'</numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>' // 0: Normal
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>' // 1: Bold
            .'<font><b/><sz val="14"/><color rgb="FF0F172A"/><name val="Calibri"/></font>' // 2: Title Bold
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>' // 3: Table Header White
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/></patternFill></fill>' // 2: Dark Slate
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>' // 3: Light Slate
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFE2E8F0"/></left>'
            .'<right style="thin"><color rgb="FFE2E8F0"/></right>'
            .'<top style="thin"><color rgb="FFE2E8F0"/></top>'
            .'<bottom style="thin"><color rgb="FFE2E8F0"/></bottom>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            .'</cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0: Normal
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0"/>' // 1: Title
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>' // 2: Subtitle/Bold
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 3: Dark Header
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>' // 4: Table Cell
            .'<xf numFmtId="164" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"><alignment horizontal="right"/></xf>' // 5: KWD Amount Bold
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"><alignment horizontal="right"/></xf>' // 6: KWD Amount Normal
            .'</cellXfs>'
            .'</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml
        $sheetData = $this->buildSheetXml($orders, $meta);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetData);

        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        return $content;
    }

    protected function buildSheetXml(Collection $orders, array $meta): string
    {
        $companyName = $this->sanitizeCellValue($meta['companyName'] ?: 'Store POS');
        $fromDate = Carbon::parse($meta['fromDate'])->format('d M Y');
        $toDate = Carbon::parse($meta['toDate'])->format('d M Y');
        $dateRangeText = ($fromDate === $toDate) ? $fromDate : "{$fromDate} to {$toDate}";
        $generatedAt = now()->format('d M Y, h:i A');

        $rows = [];
        $r = 1;

        // Row 1: Company Name
        $rows[] = "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"inlineStr\" s=\"1\"><is><t>{$companyName}</t></is></c></row>";
        $r++;

        // Row 2: Report Title
        $rows[] = "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"inlineStr\" s=\"2\"><is><t>SALES PERFORMANCE &amp; ORDER RECEIPTS REPORT</t></is></c></row>";
        $r++;

        // Row 3: Date Range & Generated info
        $rows[] = "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"inlineStr\"><is><t>Date Range: {$dateRangeText} | Generated: {$generatedAt}</t></is></c></row>";
        $r++;

        // Row 4: Empty space
        $r++;

        // Row 5: Summary Header
        $rows[] = "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"inlineStr\" s=\"2\"><is><t>PERIOD SUMMARY</t></is></c></row>";
        $r++;

        // Row 6: Summary Metrics
        $currency = $meta['currency'] ?? 'KWD';
        $decimals = isset($meta['currencyDecimals']) ? (int) $meta['currencyDecimals'] : 3;

        $revStr = number_format($meta['revenue'], $decimals, '.', '');
        $cashStr = number_format($meta['cashRevenue'], $decimals, '.', '');
        $knetStr = number_format($meta['knetRevenue'], $decimals, '.', '');
        $count = $meta['count'];

        $rows[] = "<row r=\"{$r}\">"
            ."<c r=\"A{$r}\" t=\"inlineStr\" s=\"2\"><is><t>Total Revenue:</t></is></c>"
            ."<c r=\"B{$r}\" s=\"5\"><v>{$revStr}</v></c>"
            ."<c r=\"C{$r}\" t=\"inlineStr\" s=\"2\"><is><t>Total Orders:</t></is></c>"
            ."<c r=\"D{$r}\"><v>{$count}</v></c>"
            ."<c r=\"E{$r}\" t=\"inlineStr\" s=\"2\"><is><t>Cash Total:</t></is></c>"
            ."<c r=\"F{$r}\" s=\"5\"><v>{$cashStr}</v></c>"
            ."<c r=\"G{$r}\" t=\"inlineStr\" s=\"2\"><is><t>Card Total:</t></is></c>"
            ."<c r=\"H{$r}\" s=\"5\"><v>{$knetStr}</v></c>"
            .'</row>';
        $r++;

        // Row 7: Empty space
        $r++;

        // Row 8: Table Header
        $rows[] = "<row r=\"{$r}\">"
            ."<c r=\"A{$r}\" t=\"inlineStr\" s=\"3\"><is><t>Order #</t></is></c>"
            ."<c r=\"B{$r}\" t=\"inlineStr\" s=\"3\"><is><t>Date</t></is></c>"
            ."<c r=\"C{$r}\" t=\"inlineStr\" s=\"3\"><is><t>Time</t></is></c>"
            ."<c r=\"D{$r}\" t=\"inlineStr\" s=\"3\"><is><t>Cashier Name</t></is></c>"
            ."<c r=\"E{$r}\" t=\"inlineStr\" s=\"3\"><is><t>Payment Method</t></is></c>"
            ."<c r=\"F{$r}\" t=\"inlineStr\" s=\"3\"><is><t>Items Breakdown</t></is></c>"
            ."<c r=\"G{$r}\" t=\"inlineStr\" s=\"3\"><is><t>Total ({$currency})</t></is></c>"
            .'</row>';
        $r++;

        // Rows 9+: Data Rows
        foreach ($orders as $order) {
            $orderNum = $this->sanitizeCellValue((string) $order->order_number);
            $date = $order->created_at->format('d M Y');
            $time = $order->created_at->format('h:i:s A');
            $cashier = $this->sanitizeCellValue((string) ($order->cashier_name ?: ($order->user?->name ?: 'Cashier')));
            $paymentMethodRaw = (string) $order->payment_method;
            $payment = in_array($paymentMethodRaw, ['CARD', 'KNET'], true) ? 'CARD' : $this->sanitizeCellValue($paymentMethodRaw);

            $itemsSummary = '';
            if ($order->items && count($order->items) > 0) {
                $parts = [];
                foreach ($order->items as $item) {
                    $parts[] = "{$item->quantity}x {$item->product_name}";
                }
                $itemsSummary = implode(', ', $parts);
            } else {
                $itemsSummary = 'Direct Checkout';
            }
            $itemsSummary = $this->sanitizeCellValue($itemsSummary);
            $totalAmount = number_format($order->total, $decimals, '.', '');

            $rows[] = "<row r=\"{$r}\">"
                ."<c r=\"A{$r}\" t=\"inlineStr\" s=\"4\"><is><t>{$orderNum}</t></is></c>"
                ."<c r=\"B{$r}\" t=\"inlineStr\" s=\"4\"><is><t>{$date}</t></is></c>"
                ."<c r=\"C{$r}\" t=\"inlineStr\" s=\"4\"><is><t>{$time}</t></is></c>"
                ."<c r=\"D{$r}\" t=\"inlineStr\" s=\"4\"><is><t>{$cashier}</t></is></c>"
                ."<c r=\"E{$r}\" t=\"inlineStr\" s=\"4\"><is><t>{$payment}</t></is></c>"
                ."<c r=\"F{$r}\" t=\"inlineStr\" s=\"4\"><is><t>{$itemsSummary}</t></is></c>"
                ."<c r=\"G{$r}\" s=\"6\"><v>{$totalAmount}</v></c>"
                .'</row>';
            $r++;
        }

        // Summary Total Row at end
        $rows[] = "<row r=\"{$r}\">"
            ."<c r=\"A{$r}\" t=\"inlineStr\" s=\"2\"><is><t>TOTAL</t></is></c>"
            ."<c r=\"B{$r}\" s=\"4\"/>"
            ."<c r=\"C{$r}\" s=\"4\"/>"
            ."<c r=\"D{$r}\" s=\"4\"/>"
            ."<c r=\"E{$r}\" s=\"4\"/>"
            ."<c r=\"F{$r}\" t=\"inlineStr\" s=\"2\"><is><t>{$count} Orders</t></is></c>"
            ."<c r=\"G{$r}\" s=\"5\"><v>{$revStr}</v></c>"
            .'</row>';

        $xmlRows = implode('', $rows);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols>'
            .'<col min="1" max="1" width="22" customWidth="1"/>'
            .'<col min="2" max="2" width="14" customWidth="1"/>'
            .'<col min="3" max="3" width="14" customWidth="1"/>'
            .'<col min="4" max="4" width="18" customWidth="1"/>'
            .'<col min="5" max="5" width="16" customWidth="1"/>'
            .'<col min="6" max="6" width="36" customWidth="1"/>'
            .'<col min="7" max="7" width="16" customWidth="1"/>'
            .'</cols>'
            .'<sheetData>'
            .$xmlRows
            .'</sheetData>'
            .'</worksheet>';
    }

    /**
     * Sanitize cell value against CSV / Excel Formula Injection (DDE).
     * If the text begins with =, +, -, @, \t, \r, or |, prepend a single quote.
     */
    protected function sanitizeCellValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r", '|'], true)) {
            $value = "'".$value;
        }

        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }
}
