<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Messaging\Models\MessageTemplate;
use Illuminate\Support\Facades\Validator;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $templates = MessageTemplate::where('client_account_id', $clientAccount->id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'templates' => $templates
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'content' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $template = MessageTemplate::create([
            'client_account_id' => $clientAccount->id,
            'name' => $request->name,
            'content' => $request->content,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'template' => $template
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $template = MessageTemplate::where('client_account_id', $clientAccount->id)
            ->where('id', $id)
            ->first();

        if (!$template) {
            return response()->json(['error' => 'TEMPLATE_NOT_FOUND'], 404);
        }

        $template->delete();

        return response()->json(['status' => 'SUCCESS']);
    }
}
