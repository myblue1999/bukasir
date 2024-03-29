<?php

namespace App\Http\Controllers\Api\Tenants\Reports;

use App\Http\Controllers\Controller;
use App\Models\Tenants\Selling;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CashierReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $about = tenant()->user?->about;
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        $tzName = Carbon::parse($request->start_date)->getTimezone()->getName();
        $startDate = Carbon::parse($request->start_date)->setTimezone('UTC');
        $endDate = Carbon::parse($request->end_date)->setTimezone('UTC');
        $sellings = Selling::query()
            ->select('id', 'code', 'user_id', 'created_at', 'total_price', 'total_cost')
            ->with(
                'sellingDetails:id,selling_id,product_id,qty,price,cost',
                'sellingDetails.product:id,name,initial_price,selling_price',
                'user:id,name,email'
            )
            ->when($request->filled('start_date'), function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            })
            ->when($request->filled('end_date'), function ($query) use ($endDate) {
                $query->where('created_at', '<=', $endDate);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $header = [
            'shop_name' => $about?->shop_name,
            'shop_location' => $about?->shop_location,
            'business_type' => $about?->business_type,
            'owner_name' => $about?->owner_name,
            'start_date' => $startDate->format('d F Y h:i'),
            'end_date' => $endDate->format('d F Y h:i'),
        ];
        foreach ($sellings as $selling) {
            $reports[] = [
                'transaction' => [
                    'id' => $selling->id,
                    'created_at' => Carbon::parse($selling->created_at)->setTimezone($tzName)->format('d F Y h:i'),
                    'number' => $selling->code,
                    'user' => $selling->user?->name ?? $selling->user?->email,
                    'items' => $selling->sellingDetails->map(function ($item) {
                        return [
                            'product' => $item->product?->name,
                            'quantity' => $item->qty,
                            'product_price' => $this->formatCurrency($item->product?->initial_price),
                            'price' => $this->formatCurrency($item->price),
                            'cost' => $this->formatCurrency($item->cost),
                            'net_profit' => $this->formatCurrency($item->price - $item->cost),
                        ];
                    }),
                ],
                'total' => [
                    'gross_profit' => $this->formatCurrency($selling->total_price),
                    'cost' => $this->formatCurrency($selling->total_cost),
                    'net_profit' => $this->formatCurrency($selling->total_price - $selling->total_cost),
                ],
            ];
        }
        $footer = [
            'total_gross_profit' => $this->formatCurrency($sellings->sum('total_price')),
            'total_cost' => $this->formatCurrency($sellings->sum('total_cost')),
            'total_net_profit' => $this->formatCurrency($sellings->sum('total_price') - $sellings->sum('total_cost')),
        ];

        $pdf = Pdf::loadView('reports.cashier', compact('reports', 'footer', 'header'))
            ->setPaper('a4', 'landscape');
        $pdf->output();
        $domPdf = $pdf->getDomPDF();
        $canvas = $domPdf->get_canvas();
        $canvas->page_text(720, 570, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 10, [0, 0, 0]);

        return $pdf->download('cashier-report.pdf');
    }

    private function formatCurrency($value)
    {
        return number_format($value, 0, ',', '.');
    }
}
