<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Finance\Models\WalletTransaction;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Messaging\Models\Campaign;

class ExportController extends Controller
{
    /**
     * Export wallet transactions as CSV
     */
    public function exportTransactions(Request $request)
    {
        $user = $request->user();
        $clientAccountId = $user->client_account_id;

        $query = WalletTransaction::query();
        if ($clientAccountId && !$user->isSuperAdmin()) {
            $query->where('client_account_id', $clientAccountId);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=wallet_transactions_" . date('Y_m_d_His') . ".csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Transaction ID', 'Client Account ID', 'Type', 'Amount (KES)', 'Reference Type', 'Reference ID', 'Description', 'Date & Time'];

        $callback = function () use ($transactions, $columns) {
            $file = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);

            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->id,
                    $tx->client_account_id,
                    strtoupper($tx->type),
                    number_format((float)$tx->amount, 2, '.', ''),
                    $tx->reference_type ?? 'N/A',
                    $tx->reference_id ?? 'N/A',
                    $tx->description ?? '',
                    $tx->created_at ? $tx->created_at->format('Y-m-d H:i:s') : ''
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export invoices as CSV
     */
    public function exportInvoices(Request $request)
    {
        $user = $request->user();
        $clientAccountId = $user->client_account_id;

        $query = Invoice::query();
        if ($clientAccountId && !$user->isSuperAdmin()) {
            $query->where('client_account_id', $clientAccountId);
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=invoices_" . date('Y_m_d_His') . ".csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Invoice ID', 'Invoice Number', 'Client Account ID', 'Total Amount (KES)', 'Tax (KES)', 'Status', 'Due Date', 'Paid At', 'Created At'];

        $callback = function () use ($invoices, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);

            foreach ($invoices as $inv) {
                fputcsv($file, [
                    $inv->id,
                    $inv->invoice_number ?? ('INV-' . $inv->id),
                    $inv->client_account_id,
                    number_format((float)$inv->total_amount, 2, '.', ''),
                    number_format((float)($inv->tax_amount ?? 0), 2, '.', ''),
                    strtoupper($inv->status ?? 'UNPAID'),
                    $inv->due_date ? $inv->due_date->format('Y-m-d') : 'N/A',
                    $inv->paid_at ? $inv->paid_at->format('Y-m-d H:i:s') : 'N/A',
                    $inv->created_at ? $inv->created_at->format('Y-m-d H:i:s') : ''
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export campaign delivery logs as CSV
     */
    public function exportCampaignLogs(Request $request, $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);

        $records = MessageRecord::where('campaign_id', $campaign->id)
            ->orderBy('id', 'desc')
            ->get();

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=campaign_" . $campaign->id . "_delivery_logs_" . date('Y_m_d_His') . ".csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Record ID', 'Recipient MSISDN', 'Carrier Status', 'Network DLR Code', 'Charged Price (KES)', 'MNO Message ID', 'Dispatched At'];

        $callback = function () use ($records, $columns) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columns);

            foreach ($records as $r) {
                fputcsv($file, [
                    $r->id,
                    $r->recipient_phone,
                    strtoupper($r->status ?? 'PENDING'),
                    $r->network_status_code ?? 'DELIVRD',
                    number_format((float)$r->price, 2, '.', ''),
                    $r->mno_message_id ?? 'N/A',
                    $r->created_at ? $r->created_at->format('Y-m-d H:i:s') : ''
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
