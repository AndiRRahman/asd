<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use App\Domain\Payment\RefundService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderShipment;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $search = $request->query('q');

        $orders = Order::query()
            ->with(['user:id,name', 'items'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$search}%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
            ->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('panel/orders/index', [
            'orders' => [
                'data' => $orders->getCollection()->map(fn ($o) => [...Present::orderSummary($o), 'buyer' => $o->user->name]),
                'current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total(),
            ],
            'counts' => Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => ['status' => $status, 'q' => $search],
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        $order = Order::query()->where('code', $code)->with(['user', 'refunds'])->firstOrFail();
        $this->orders->expireIfDue($order);

        return Inertia::render('panel/orders/show', [
            'order' => Present::orderDetail($order->refresh(), forBuyer: false),
            'buyer' => ['name' => $order->user->name, 'email' => $order->user->email, 'phone' => $request->user()->can('ekspor.data_pribadi') ? $order->user->phone : Present::maskPhone($order->user->phone)],
            'refunds' => $order->refunds->map(fn (OrderRefund $r) => [
                'id' => $r->hashid, 'amount' => $r->amount, 'bank_name' => $r->bank_name, 'account_number' => $r->account_number,
                'account_holder' => $r->account_holder, 'reason' => $r->reason, 'status' => $r->status,
                'mine' => $r->requested_by === $request->user()->id, 'refunded_at' => $r->refunded_at?->toIso8601String(),
            ]),
            'canRefund' => $request->user()->can('refund.proses'),
        ]);
    }

    public function tracking(Request $request, OrderShipment $shipment): RedirectResponse
    {
        $data = $request->validate(['tracking_number' => ['required', 'string', 'max:60']]);
        $this->orders->setTracking($shipment, $data['tracking_number'], $request->user());

        return $this->done('Nomor resi tersimpan.');
    }

    public function readyForPickup(Request $request, OrderShipment $shipment): RedirectResponse
    {
        $this->orders->markReadyForPickup($shipment, $request->user());

        return $this->done('Paket ditandai siap diambil. Pembeli melihat kode ambil di halaman pesanannya.');
    }

    public function verifyPickup(Request $request, OrderShipment $shipment): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $this->orders->verifyPickup($shipment, $data['code']);

        return $this->done('Kode cocok. Paket ditandai sudah diambil.');
    }

    public function delivered(Request $request, OrderShipment $shipment): RedirectResponse
    {
        $data = $request->validate(['photo' => ['required', 'image', 'max:10240'], 'receiver_name' => ['required', 'string', 'max:120']]);
        $this->orders->markLocalDelivered($shipment, $data['photo']->store('serah-terima', 'local'), $data['receiver_name'], $request->user());

        return $this->done('Foto serah terima tersimpan.');
    }

    public function cancel(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $order = Order::query()->where('code', $code)->firstOrFail();
        abort_unless($order->status === OrderStatus::Diproses, 409, 'Admin hanya bisa membatalkan pesanan berstatus Diproses.');
        $this->orders->cancelByAdmin($order, $request->user(), $data['reason']);

        return $this->done('Pesanan dibatalkan dan stok dikembalikan. Catat pengembalian dana di bawah.');
    }

    public function requestRefund(Request $request, string $code, RefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'], 'bank_name' => ['required', 'string', 'max:60'],
            'account_number' => ['required', 'string', 'max:40'], 'account_holder' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $refunds->request(Order::query()->where('code', $code)->firstOrFail(), $request->user(), $data);

        return $this->done('Pengajuan refund tercatat. Admin Transaksi lain perlu menyetujui dan mengunggah bukti transfer.');
    }

    public function approveRefund(Request $request, OrderRefund $refund, RefundService $refunds): RedirectResponse
    {
        $request->validate(['proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        $refunds->approve($refund, $request->user(), $request->file('proof'));

        return $this->done('Refund selesai dan tercatat.');
    }

    /** Label alamat siap cetak per paket. */
    public function label(string $code): View
    {
        return view('label', ['order' => Order::query()->where('code', $code)->with('shipments.items')->firstOrFail()]);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
