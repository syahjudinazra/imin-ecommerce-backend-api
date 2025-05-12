<?php

namespace App\Http\Controllers\Api;

use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function initiateMidtransPayment($order)
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = false;

        $items = $order->items->map(function ($item) {
            return [
                'id' => $item->product_id,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'name' => $item->product->name
            ];
        });

        $params = [
            'transaction_details' => [
                'order_id' => 'ORDER-' . $order->id,
                'gross_amount' => $items->sum(fn($i) => $i['price'] * $i['quantity'])
            ],
            'item_details' => $items->toArray(),
            'customer_details' => [
                'email' => $order->user->email,
                'first_name' => $order->user->name,
            ]
        ];

        $snap = Snap::createTransaction($params);
        return $snap->redirect_url;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $cartItems = $user->cartItems()->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $order = Order::create(['user_id' => $user->id]);

        foreach ($cartItems as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->product->price
            ]);
        }

        $user->cartItems()->delete(); // clear cart

        // hit Midtrans payment URL (lihat bawah 👇)
        $paymentUrl = $this->initiateMidtransPayment($order);
        $order->update(['payment_url' => $paymentUrl]);

        // Send email via Firebase (lihat bawah 👇)
        $this->sendFirebaseEmail($user->email, 'Order created!', 'Thanks for your order!');

        return response()->json(['message' => 'Order placed', 'payment_url' => $paymentUrl]);
    }

    public function sendFirebaseEmail($to, $subject, $message)
    {
        Http::post(env('FIREBASE_EMAIL_ENDPOINT'), [
            'to' => $to,
            'subject' => $subject,
            'message' => $message
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
