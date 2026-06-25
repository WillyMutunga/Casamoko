<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Messaging\Models\MessageRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    /**
     * Get paginated logs for the authenticated client.
     */
    public function getLogs(Request $request)
    {
        $clientId = $request->attributes->get('client_account_id');
        if (!$clientId) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $query = MessageRecord::with(['campaign', 'contact', 'route'])
            ->whereHas('campaign', function ($q) use ($clientId) {
                $q->where('client_account_id', $clientId);
            });

        if ($request->has('search')) {
            $search = strtolower($request->query('search'));
            $query->whereHas('contact', function ($q) use ($search) {
                // Just matching partial msisdn for searching, not true salted hash right now
                $q->where('msisdn', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        // Format for frontend
        $formatted = $logs->map(function ($log) {
            $msisdn = $log->contact ? $log->contact->msisdn : 'Unknown';
            // Simple masking to match UI's 'Salted MSISDN Hash' simulation
            $masked = substr($msisdn, 0, 4) . '****' . substr($msisdn, -3);

            return [
                'id' => $log->id,
                'msisdn_hash' => $masked,
                'network' => 'Safaricom', // Default for now
                'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                'status' => $log->status,
                'error_code' => $log->network_status_code ?: 'N/A'
            ];
        });

        return response()->json([
            'success' => true,
            'logs' => $formatted,
            'total' => $logs->total()
        ]);
    }

    /**
     * Export all logs as CSV for the authenticated client.
     */
    public function exportCsv(Request $request)
    {
        $clientId = $request->attributes->get('client_account_id');
        if (!$clientId) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $query = MessageRecord::with(['campaign', 'contact'])
            ->whereHas('campaign', function ($q) use ($clientId) {
                $q->where('client_account_id', $clientId);
            })
            ->orderBy('created_at', 'desc');

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Record ID', 'MSISDN', 'Status', 'Network/Error Code', 'Timestamp', 'Cost']);

            $query->chunk(500, function ($records) use ($handle) {
                foreach ($records as $record) {
                    $msisdn = $record->contact ? $record->contact->msisdn : 'Unknown';
                    fputcsv($handle, [
                        $record->id,
                        $msisdn,
                        $record->status,
                        $record->network_status_code ?: 'N/A',
                        $record->created_at->format('Y-m-d H:i:s'),
                        $record->price
                    ]);
                }
            });

            fclose($handle);
        });

        $filename = "delivery_reports_" . date('Y-m-d_His') . ".csv";
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
