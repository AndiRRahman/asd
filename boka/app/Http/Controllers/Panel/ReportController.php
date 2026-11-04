<?php

namespace App\Http\Controllers\Panel;

use App\Domain\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReportExport;
use App\Support\Present;
use App\Support\Reports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan dan ekspor (bagian 4.6). Ekspor memakai filter yang sedang aktif,
 * sehingga isi file sama dengan tabel di layar.
 */
class ReportController extends Controller
{
    public function __construct(private Reports $reports) {}

    public function index(Request $request): Response
    {
        [$from, $to, $filters] = $this->period($request);

        return Inertia::render('panel/reports', [
            'filters' => $filters,
            'sales' => $this->reports->salesByDay($from, $to),
            'producers' => $this->reports->salesByProducer($from, $to),
            'topProducts' => $this->reports->topProducts($from, $to),
            'searchesWithoutResults' => $this->reports->searchKeywords($from, $to, true),
            'popularSearches' => $this->reports->searchKeywords($from, $to, false)->take(15)->values(),
            'lowStock' => $this->reports->lowStock(),
            'vouchers' => $this->reports->voucherUsage($from, $to),
            'canExportPersonal' => $request->user()->can('ekspor.data_pribadi'),
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        [$from, $to, $filters] = $this->period($request);
        $user = $request->user();
        $personal = $user->can('ekspor.data_pribadi');

        [$header, $rows] = match ($report) {
            'penjualan' => [['Tanggal', 'Pesanan', 'Omzet', 'Rata-rata'], $this->reports->salesByDay($from, $to)->map(fn ($r) => array_values($r))],
            'produsen' => [['Produsen', 'Jenis', 'Item terjual', 'Pesanan', 'Omzet'], $this->reports->salesByProducer($from, $to)->map(fn ($r) => array_values($r))],
            'pencarian-tanpa-hasil' => [['Kata kunci', 'Jumlah', 'Terakhir dicari'], $this->reports->searchKeywords($from, $to, true)->map(fn ($r) => array_values($r))],
            'stok-menipis' => [['SKU', 'Produk', 'Varian', 'Titik asal', 'Stok', 'Ambang'], $this->reports->lowStock()->map(fn ($r) => array_values($r))],
            // Data Pembeli: nama, WhatsApp, alamat disamarkan untuk peran tanpa izin (4.6).
            'pesanan' => [['Kode', 'Tanggal (WITA)', 'Status', 'Pembeli', 'WhatsApp', 'Kecamatan', 'Total'], Order::query()->with('user')->whereBetween('created_at', [$from, $to])->orderBy('id')->get()->map(fn ($o) => [
                $o->code, $o->created_at->timezone('Asia/Makassar')->format('Y-m-d H:i'), $o->status->value,
                $personal ? $o->user->name : Present::maskName($o->user->name),
                $personal ? $o->user->phone : Present::maskPhone($o->user->phone),
                $o->address_snapshot['district'] ?? '', $o->grand_total,
            ])],
            default => abort(404),
        };

        if ($rows->count() > 5000) {
            throw new BusinessRuleException('Ekspor di atas 5.000 baris diproses lewat antrean. Persempit rentang tanggal untuk unduhan langsung.');
        }

        ReportExport::query()->create([
            'user_id' => $user->id, 'report' => $report, 'filters' => $filters, 'format' => 'csv',
            'row_count' => $rows->count(), 'contains_personal_data' => $report === 'pesanan' && $personal,
        ]);
        activity()->causedBy($user)->withProperties(['report' => $report, 'filters' => $filters, 'rows' => $rows->count()])->log('Ekspor laporan');

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, "boka-{$report}-{$filters['from']}-{$filters['to']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Rentang maksimal 12 bulan; tanggal dibaca dalam WITA. */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now('Asia/Makassar')->subDays(29)->toDateString(), 'Asia/Makassar')->startOfDay();
        $to = Carbon::parse($data['to'] ?? now('Asia/Makassar')->toDateString(), 'Asia/Makassar')->endOfDay();

        if ($from->diffInMonths($to) > 12) {
            throw new BusinessRuleException('Rentang laporan maksimal 12 bulan.');
        }

        return [$from->utc(), $to->utc(), ['from' => $from->timezone('Asia/Makassar')->toDateString(), 'to' => $to->timezone('Asia/Makassar')->toDateString()]];
    }
}
