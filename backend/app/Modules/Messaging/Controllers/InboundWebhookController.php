<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Messaging\Models\SenderID;

class InboundWebhookController extends Controller
{
    /**
     * Handle incoming SMS from SMPP Gateway/Aggregator.
     */
    public function handle(Request $request)
    {
        Log::info('Inbound Webhook Received:', $request->all());

        // Standard SMPP/API Payload structure example
        $validated = $request->validate([
            'source_addr' => 'required|string', // The subscriber phone number
            'dest_addr' => 'required|string',   // The shortcode or Sender ID
            'message' => 'required|string',     // The SMS content
        ]);

        $msisdn = preg_replace('/[^0-9]/', '', $validated['source_addr']);
        if (str_starts_with($msisdn, '0')) {
            $msisdn = '254' . substr($msisdn, 1);
        }

        $message = strtoupper(trim($validated['message']));
        $destination = $validated['dest_addr'];

        // Determine Opt-out intents
        $optOutKeywords = ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'QUIT'];

        if (in_array($message, $optOutKeywords)) {
            Log::info("Opt-out keyword '{$message}' received from {$msisdn} on destination {$destination}");

            // Try to find which client account this destination belongs to
            // If it's a dedicated shortcode or Sender ID:
            $sender = SenderID::where('sender_id', $destination)->first();

            if ($sender) {
                // Find or create the contact for this specific client and mark as blocklisted in metadata
                $hash = Contact::hashMsisdn($msisdn);
                
                $contact = Contact::firstOrCreate(
                    ['client_account_id' => $sender->client_account_id, 'msisdn_hash' => $hash],
                    ['msisdn' => $msisdn, 'name' => 'Opt-out Subscriber']
                );

                $metadata = $contact->metadata ?? [];
                $metadata['is_blocklisted'] = true;
                $metadata['opt_out_date'] = now()->toIso8601String();
                $metadata['opt_out_reason'] = "Sent $message to $destination";

                $contact->metadata = $metadata;
                $contact->save();

                Log::info("Successfully blocklisted contact {$msisdn} for client {$sender->client_account_id}");
            } else {
                // If the shortcode is shared, you'd need more complex logic to block across all tenants,
                // but for enterprise dedicated routing, this applies:
                Log::warning("Destination {$destination} not mapped to a dedicated client account.");
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Inbound SMS processed successfully'
        ]);
    }
}
