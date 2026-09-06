<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Services\SalesExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function exportPdf(Request $request)
    {
        ['from' => $from, 'to' => $to, 'filename' => $fileName] = $this->resolveDateRange($request, 'pdf');

        $orders = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with(['items', 'user'])
            ->orderByDesc('created_at')
            ->get();

        $revenue = (float) $orders->sum('total');
        $cashRevenue = (float) $orders->where('payment_method', 'CASH')->sum('total');
        $knetRevenue = (float) $orders->whereIn('payment_method', ['CARD', 'KNET'])->sum('total');
        $count = $orders->count();

        $companyName = Setting::get('company_name', 'Store POS');
        $siteLogo = Setting::get('site_logo');
        $currency = Setting::getCurrency();
        $currencyDecimals = Setting::getCurrencyDecimals();

        // Convert logo to base64 for reliable DomPDF local rendering
        $logoBase64 = null;
        if (! empty($siteLogo)) {
            $logoPath = null;
            if (str_contains($siteLogo, '/storage/')) {
                $relativeStoragePath = substr($siteLogo, strpos($siteLogo, '/storage/') + 9);
                $fullPath = storage_path('app/public/'.$relativeStoragePath);
                if (file_exists($fullPath)) {
                    $logoPath = $fullPath;
                }
            } elseif (file_exists(public_path($siteLogo))) {
                $logoPath = public_path($siteLogo);
            }

            if ($logoPath && file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $data = file_get_contents($logoPath);
                $logoBase64 = 'data:image/'.$type.';base64,'.base64_encode($data);
            }
        }

        $fromDateFormatted = Carbon::parse($from)->format('d M Y');
        $toDateFormatted = Carbon::parse($to)->format('d M Y');
        $dateRangeText = ($from === $to) ? $fromDateFormatted : "{$fromDateFormatted} to {$toDateFormatted}";

        $pdf = Pdf::loadView('reports.sales-pdf', [
            'orders' => $orders,
            'companyName' => $companyName,
            'logoBase64' => $logoBase64,
            'dateRangeText' => $dateRangeText,
            'revenue' => $revenue,
            'cashRevenue' => $cashRevenue,
            'knetRevenue' => $knetRevenue,
            'count' => $count,
            'currency' => $currency,
            'currencyDecimals' => $currencyDecimals,
            'generatedAt' => now()->format('d M Y, h:i A'),
            'generatedBy' => Auth::user()?->name ?? 'Admin',
        ])->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
    }

    public function exportXlsx(Request $request, SalesExcelExporter $exporter): StreamedResponse
    {
        ['from' => $from, 'to' => $to, 'filename' => $fileName] = $this->resolveDateRange($request, 'xlsx');

        $orders = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with(['items', 'user'])
            ->orderByDesc('created_at')
            ->get();

        $meta = [
            'companyName' => Setting::get('company_name', 'Store POS'),
            'fromDate' => $from,
            'toDate' => $to,
            'revenue' => (float) $orders->sum('total'),
            'count' => $orders->count(),
            'cashRevenue' => (float) $orders->where('payment_method', 'CASH')->sum('total'),
            'knetRevenue' => (float) $orders->whereIn('payment_method', ['CARD', 'KNET'])->sum('total'),
            'currency' => Setting::getCurrency(),
            'currencyDecimals' => Setting::getCurrencyDecimals(),
        ];

        $content = $exporter->generate($orders, $meta);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Parse and sanitize date range query parameters against injection.
     *
     * @return array{from: string, to: string, filename: string}
     */
    protected function resolveDateRange(Request $request, string $extension): array
    {
        $rawFrom = (string) $request->query('from', '');
        $rawTo = (string) $request->query('to', '');

        $from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom) && strtotime($rawFrom))
            ? $rawFrom
            : now()->toDateString();

        $to = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo) && strtotime($rawTo))
            ? $rawTo
            : $from;

        if ($from > $to) {
            $temp = $from;
            $from = $to;
            $to = $temp;
        }

        $safeExt = in_array(strtolower($extension), ['pdf', 'xlsx'], true) ? strtolower($extension) : 'pdf';
        $filename = "sales_report_{$from}_to_{$to}.{$safeExt}";

        return [
            'from' => $from,
            'to' => $to,
            'filename' => $filename,
        ];
    }
}
