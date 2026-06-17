<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Models\ActivityLog;

class TeamController extends Controller
{
    /**
     * List all sub-users under the authenticated user's organization (FR-UAM-003).
     */
    public function listTeam(Request $request)
    {
        $user = $request->user();
        
        if (!$user->client_account_id) {
            return response()->json([
                'status' => 'SUCCESS',
                'team' => []
            ]);
        }

        $team = User::where('client_account_id', $user->client_account_id)->get();

        return response()->json([
            'status' => 'SUCCESS',
            'team' => $team
        ]);
    }

    /**
     * Add a sub-user to the organization account (FR-UAM-003).
     */
    public function addTeamMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'sub_role' => 'required|string|in:CLIENT_ADMIN,CAMPAIGN_MANAGER,ANALYST,FINANCE_OFFICER',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $admin = $request->user();

        if ($admin->role_tier !== 'CLIENT' || $admin->sub_role !== 'CLIENT_ADMIN') {
            return response()->json([
                'error' => 'PRIVILEGE_UNAUTHORIZED',
                'message' => 'Only the CLIENT_ADMIN of an organization is authorized to manage team users.'
            ], 403);
        }

        $subUser = User::create([
            'client_account_id' => $admin->client_account_id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role_tier' => 'CLIENT',
            'sub_role' => $request->input('sub_role'),
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        // Write immutable audit log
        ActivityLog::log('USER_CREATED', "Onboarded sub-user '{$subUser->name}' with role tier '{$subUser->sub_role}'.");

        // Dispatch Welcome Email
        \Illuminate\Support\Facades\Mail::to($subUser->email)->queue(
            new \App\Mail\WelcomeEmail($subUser, $request->input('password'))
        );

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Sub-user '{$subUser->name}' onboarded successfully as '{$subUser->sub_role}'!",
            'user' => $subUser
        ], 201);
    }

    /**
     * List all immutable action logs for the organization (FR-UAM-008).
     */
    public function listActivities(Request $request)
    {
        $user = $request->user();

        if (!$user->client_account_id) {
            return response()->json([
                'status' => 'SUCCESS',
                'activities' => []
            ]);
        }

        $logs = ActivityLog::with('user')
            ->where('client_account_id', $user->client_account_id)
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $logs->map(function ($l) {
            return [
                'id' => $l->id,
                'action' => $l->action,
                'description' => $l->description,
                'ip_address' => $l->ip_address,
                'user_agent' => $l->user_agent,
                'username' => $l->user ? $l->user->name : 'System',
                'created_at' => $l->created_at->toIso8601String()
            ];
        });

        return response()->json([
            'status' => 'SUCCESS',
            'activities' => $formatted
        ]);
    }
}
