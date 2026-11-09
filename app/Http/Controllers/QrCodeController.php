<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QR dell’ordine: contiene solo il codice. Lo vede il proprietario dell’ordine o chi lavora al banco.
 */
class QrCodeController extends Controller
{
    public function show(Request $request, string $orderNumber): View
    {
        return view('qr.code', ['order' => $this->findAuthorized($request, $orderNumber)]);
    }

    public function image(Request $request, string $orderNumber): Response
    {
        $order = $this->findAuthorized($request, $orderNumber);

        $svg = QrCode::format('svg')->size(300)->margin(1)->generate($order->order_number);

        $headers = ['Content-Type' => 'image/svg+xml'];

        if ($request->has('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="order-'.$order->order_number.'-qr.svg"';
        }

        return response((string) $svg, 200, $headers);
    }

    private function findAuthorized(Request $request, string $orderNumber): Order
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $user = $request->user();

        $isOwner = $order->user_id === $user->id;
        $isStaff = $user->hasPermission(Permission::COUNTER_USE)
            || $user->hasPermission(Permission::ORDERS_MARKET_MANAGE)
            || $user->hasPermission(Permission::ORDERS_DELIVERY_MANAGE);

        abort_unless($isOwner || $isStaff, 404);

        return $order;
    }
}
