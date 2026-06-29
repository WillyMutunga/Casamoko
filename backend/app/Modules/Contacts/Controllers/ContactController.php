<?php

namespace App\Modules\Contacts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactList;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    /**
     * Get all contact lists for the authenticated client account.
     */
    public function getLists(Request $request)
    {
        $clientAccountId = $request->user()->client_account_id;
        
        $lists = ContactList::where('client_account_id', $clientAccountId)->get();
        
        return response()->json([
            'contact_lists' => $lists
        ]);
    }

    public function createList(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'subscription_source' => 'nullable|string',
        ]);

        $clientAccountId = $request->user()->client_account_id;

        $list = ContactList::create([
            'client_account_id' => $clientAccountId,
            'name' => $request->name,
            'description' => $request->description ?? '',
            'tags' => $request->tags ?? [],
            'subscription_source' => $request->subscription_source ?? 'Dashboard',
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'contact_list' => $list
        ]);
    }

    public function importContacts(Request $request, $listId)
    {
        $request->validate([
            'contacts' => 'required|array',
            'contacts.*.msisdn' => 'required|string',
            'contacts.*.name' => 'nullable|string',
            'contacts.*.metadata' => 'nullable|array',
        ]);

        $clientAccountId = $request->user()->client_account_id;
        
        $list = ContactList::where('client_account_id', $clientAccountId)
                           ->where('id', $listId)
                           ->firstOrFail();

        $importedCount = 0;

        foreach ($request->contacts as $contactData) {
            $contact = Contact::firstOrCreate(
                [
                    'client_account_id' => $clientAccountId,
                    'msisdn' => $contactData['msisdn'],
                ],
                [
                    'name' => $contactData['name'] ?? 'Subscriber',
                    'metadata' => $contactData['metadata'] ?? [],
                ]
            );

            $list->contacts()->syncWithoutDetaching([$contact->id]);
            $importedCount++;
        }

        // The contact_count is now dynamically appended by the ContactList model accessor

        return response()->json([
            'status' => 'SUCCESS',
            'imported_count' => $importedCount,
            'contact_list' => $list
        ]);
    }

    /**
     * Get all contacts for the authenticated client account.
     */
    public function getContacts(Request $request)
    {
        $clientAccountId = $request->user()->client_account_id;
        $listId = $request->query('list_id');
        
        $query = DB::table('contact_list_members')
            ->join('contacts', 'contacts.id', '=', 'contact_list_members.contact_id')
            ->where('contacts.client_account_id', $clientAccountId)
            ->select('contacts.*', 'contact_list_members.contact_list_id as list_id');

        if ($listId) {
            $query->where('contact_list_members.contact_list_id', $listId);
        }
        
        $contacts = $query->paginate(100);
        
        return response()->json([
            'contacts' => $contacts
        ]);
    }

    public function exportContacts(Request $request, $listId)
    {
        $clientAccountId = $request->user()->client_account_id;
        
        $list = ContactList::where('client_account_id', $clientAccountId)
                           ->where('id', $listId)
                           ->firstOrFail();

        $callback = function() use ($list, $clientAccountId) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Phone MSISDN', 'Subscriber Name', 'Dynamic Attributes']);

            DB::table('contact_list_members')
                ->join('contacts', 'contacts.id', '=', 'contact_list_members.contact_id')
                ->where('contacts.client_account_id', $clientAccountId)
                ->where('contact_list_members.contact_list_id', $list->id)
                ->orderBy('contacts.id')
                ->chunk(1000, function ($contacts) use ($file) {
                    foreach ($contacts as $contact) {
                        fputcsv($file, [
                            $contact->msisdn,
                            $contact->name,
                            $contact->metadata
                        ]);
                    }
                });

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="List_' . $list->id . '_Contacts.csv"',
        ]);
    }
}
