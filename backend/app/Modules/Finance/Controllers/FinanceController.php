<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Finance\Models\WalletTransaction;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\MpesaPayment;
use App\Modules\Finance\Services\LedgerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FinanceController extends Controller
{
    public function transactions(Request $request)
    {
        $transactions = WalletTransaction::where('client_account_id', $request->user()->client_account_id)->get();
        
        return response()->json([
            'transactions' => $transactions
        ]);
    }

    public function invoices(Request $request)
    {
        $invoices = Invoice::where('client_account_id', $request->user()->client_account_id)->get();
        
        return response()->json([
            'invoices' => $invoices
        ]);
    }

    public function mpesa(Request $request)
    {
        $mpesa_payments = MpesaPayment::where('client_account_id', $request->user()->client_account_id)->get();
        
        return response()->json([
            'mpesa_payments' => $mpesa_payments
        ]);
    }

    /**
     * Initiate M-Pesa STK Push
     */
    public function stkPush(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:10',
        ]);

        $clientAccount = $request->user()->clientAccount;
        if (!$clientAccount) {
            return response()->json(['error' => 'UNAUTHORIZED'], 403);
        }

        $phone = preg_replace('/[^0-9]/', '', $request->input('phone_number'));
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }

        $amount = (float) $request->input('amount');

        $consumerKey = env('MPESA_CONSUMER_KEY');
        $consumerSecret = env('MPESA_CONSUMER_SECRET');
        $shortcode = env('MPESA_SHORTCODE');
        $passkey = env('MPESA_PASSKEY');
        $callbackUrl = env('MPESA_CALLBACK_URL');

        // 1. Generate OAuth Token
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        $authResponse = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

        if (!$authResponse->successful()) {
            Log::error('M-Pesa Auth failed: ' . $authResponse->body());
            return response()->json(['error' => 'PAYMENT_GATEWAY_ERROR', 'message' => 'Failed to authenticate with payment gateway.'], 502);
        }

        $accessToken = $authResponse->json()['access_token'];

        // 2. Initiate STK Push
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        $stkPayload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => 'Casamoko',
            'TransactionDesc' => 'Wallet Topup'
        ];

        $stkResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', $stkPayload);

        if (!$stkResponse->successful()) {
            Log::error('M-Pesa STK Push failed: ' . $stkResponse->body());
            return response()->json(['error' => 'PAYMENT_GATEWAY_ERROR', 'message' => 'Failed to initiate STK push.'], 502);
        }

        $stkData = $stkResponse->json();
        $checkoutRequestId = $stkData['CheckoutRequestID'] ?? null;

        if (!$checkoutRequestId) {
            return response()->json(['error' => 'PAYMENT_GATEWAY_ERROR', 'message' => 'Invalid response from payment gateway.'], 502);
        }

        $payment = MpesaPayment::create([
            'client_account_id' => $clientAccount->id,
            'merchant_request_id' => $stkData['MerchantRequestID'] ?? ('req_' . uniqid()),
            'checkout_request_id' => $checkoutRequestId,
            'amount' => $amount,
            'mpesa_receipt_number' => null,
            'phone_number' => $phone,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'STK Push initiated successfully. Please enter your M-Pesa PIN.',
            'checkout_request_id' => $checkoutRequestId,
        ]);
    }

    /**
     * Callback for Daraja API
     */
    public function stkCallback(Request $request, LedgerService $ledgerService)
    {
        Log::info('M-Pesa STK Callback received:', $request->all());

        $content = json_decode($request->getContent(), true);
        if (!isset($content['Body']['stkCallback'])) {
            return response()->json(['message' => 'Invalid payload']);
        }

        $callback = $content['Body']['stkCallback'];
        $checkoutRequestId = $callback['CheckoutRequestID'];
        $resultCode = $callback['ResultCode'];

        $payment = MpesaPayment::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$payment) {
            Log::error("M-Pesa Callback: Payment not found for CheckoutRequestID $checkoutRequestId");
            return response()->json(['message' => 'Not found']);
        }

        if ($payment->status === 'COMPLETED') {
            return response()->json(['message' => 'Already processed']);
        }

        if ($resultCode == 0) {
            // Success! Extract Receipt Number
            $receiptNumber = null;
            $items = $callback['CallbackMetadata']['Item'] ?? [];
            foreach ($items as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'];
                }
            }

            $payment->update([
                'status' => 'COMPLETED',
                'mpesa_receipt_number' => $receiptNumber
            ]);

            // Atomically credit the wallet
            try {
                $ledgerService->credit(
                    $payment->client_account_id,
                    $payment->amount,
                    'TOPUP_MPESA',
                    MpesaPayment::class,
                    $payment->id,
                    "M-Pesa Topup Ref: $receiptNumber"
                );
            } catch (\Exception $e) {
                Log::error("Failed to credit wallet for M-Pesa Topup $receiptNumber: " . $e->getMessage());
            }

        } else {
            // Failed/Cancelled
            $payment->update(['status' => 'FAILED']);
        }

        return response()->json(['message' => 'Accepted']);
    }
}
