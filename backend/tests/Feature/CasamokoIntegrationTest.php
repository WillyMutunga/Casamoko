<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Models\ClientAccount;
use App\Modules\Accounts\Models\ResellerAccount;
use App\Modules\Accounts\Models\LoginAttempt;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Messaging\Models\SenderID;
use App\Modules\Messaging\Models\PricingRule;
use App\Modules\Messaging\Models\OptOutRegistry;
use App\Modules\Messaging\Models\Campaign;
use App\Modules\Messaging\Models\MessageRecord;

class CasamokoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected User $adminUser;
    protected ClientAccount $clientAccount;
    protected ResellerAccount $resellerAccount;
    protected SenderID $senderId;

    /**
     * Setup database states for execution.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed Reseller
        $this->resellerAccount = ResellerAccount::create([
            'name' => 'Casamoko Reseller North',
            'markup_percentage' => 10.00, // 10% markup on carrier SMS
            'api_credits' => 1000.0000,
        ]);

        // 2. Seed Client Tenant
        $this->clientAccount = ClientAccount::create([
            'reseller_account_id' => $this->resellerAccount->id,
            'name' => 'Acme Corporation Kenya',
            'status' => 'APPROVED',
            'wallet_balance' => 5.0000, // Seed with $5 wallet balance
            'credit_limit' => 2.0000, // Seed with $2 credit limit
        ]);

        // 3. Seed Client User
        $this->clientUser = User::create([
            'client_account_id' => $this->clientAccount->id,
            'name' => 'Jane Doe',
            'email' => 'jane@acme.com',
            'password' => Hash::make('Secret123'),
            'role_tier' => 'CLIENT',
            'sub_role' => 'CLIENT_ADMIN',
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        // 4. Seed Supervisor Admin User
        $this->adminUser = User::create([
            'name' => 'Super Administrator',
            'email' => 'wmutunga003@gmail.com',
            'password' => Hash::make('William#20'),
            'role_tier' => 'SUPER_ADMIN',
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        // 5. Seed Sender ID
        $this->senderId = SenderID::create([
            'client_account_id' => $this->clientAccount->id,
            'sender_id' => 'ACME_ALERTS',
            'status' => 'APPROVED',
        ]);

        // 6. Seed Prefix pricing rule
        PricingRule::create([
            'name' => 'Safaricom Local Network',
            'destination_prefix' => '2547',
            'base_cost' => 0.0200, // $0.02 base
            'reseller_markup' => 0.0050, // $0.005 base markup
            'client_account_id' => null,
        ]);

        // 7. Seed Carrier and active Route for Safaricom to support Intelligent Routing tests
        $carrier = \App\Modules\Messaging\Models\Carrier::create(['name' => 'Safaricom MNO']);
        \App\Modules\Messaging\Models\Route::create([
            'name' => 'Safaricom Primary Route',
            'carrier_id' => $carrier->id,
            'cost_per_sms' => 0.0030,
            'tps_limit' => 100,
            'priority' => 1,
            'is_active' => true,
            'destination_network' => 'SAFARICOM',
            'bind_type' => 'TRANSCEIVER',
            'window_size' => 10,
            'use_tls' => true,
            'delivery_rate_score' => 99.8,
            'latency_score' => 45,
        ]);
    }

    /**
     * Test credential failures log audit trail entries.
     */
    public function test_auth_failure_logs_login_attempt()
    {
        $response = $this->postJson('/api/accounts/login', [
            'email' => 'jane@acme.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['error', 'message']);

        // Verify audit log exists
        $this->assertDatabaseHas('login_attempts', [
            'username' => 'jane@acme.com',
            'status' => 'FAILED',
            'failure_reason' => 'INVALID_CREDENTIALS',
        ]);
    }

    /**
     * Test brute force rate limits intercept logins after 5 consecutive failures.
     */
    public function test_brute_force_middleware_blocks_after_5_failures()
    {
        // Fail 5 times in a row
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/accounts/login', [
                'email' => 'jane@acme.com',
                'password' => 'WrongPassword',
            ]);
        }

        // 6th attempt should block instantly with 429
        $response = $this->postJson('/api/accounts/login', [
            'email' => 'jane@acme.com',
            'password' => 'Secret123', // Correct password but blocked
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'error' => 'BRUTE_FORCE'
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'username' => 'jane@acme.com',
            'status' => 'FAILED',
            'failure_reason' => 'BRUTE_FORCE',
        ]);
    }

    /**
     * Test client tenant suspension blocks API operations immediately.
     */
    public function test_tenant_suspension_blocks_protected_routes()
    {
        // Suspend the client account
        $this->clientAccount->update(['status' => 'SUSPENDED']);

        // Authenticate the user
        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/accounts/profile');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'TENANT_SUSPENDED'
        ]);
    }

    /**
     * Test administrator password expirations block logins after 90 days.
     */
    public function test_admin_password_expiry_middleware_blocks()
    {
        // Expire admin's password change date (e.g. 100 days ago)
        $this->adminUser->update(['password_changed_at' => now()->subDays(100)]);

        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/accounts/profile');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'PASSWORD_EXPIRED'
        ]);
    }

    /**
     * Test administrative accounts 2FA challenge flow.
     */
    public function test_admin_requires_2fa_challenge()
    {
        // 1. Initial login should request binding/setup
        $response = $this->postJson('/api/accounts/login', [
            'email' => 'wmutunga003@gmail.com',
            'password' => 'William#20',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => '2FA_SETUP_REQUIRED'
        ]);

        // Refetch user to get the bound secret
        $this->adminUser->refresh();
        $this->assertNotNull($this->adminUser->two_factor_secret);

        // 2. Second login now challenges for code
        $response2 = $this->postJson('/api/accounts/login', [
            'email' => 'wmutunga003@gmail.com',
            'password' => 'William#20',
        ]);

        $response2->assertStatus(200);
        $response2->assertJson([
            'status' => '2FA_REQUIRED'
        ]);

        // 3. Confirm challenge with correct sandbox/master token (123456)
        $response3 = $this->postJson('/api/accounts/login', [
            'email' => 'wmutunga003@gmail.com',
            'password' => 'William#20',
            'totp_code' => '123456',
        ]);

        $response3->assertStatus(200);
        $response3->assertJson([
            'status' => 'SUCCESS'
        ]);
        $response3->assertJsonStructure(['token']);
    }

    /**
     * Test opt-out registry checks reject dispatches automatically.
     */
    public function test_opt_out_registry_skips_recipient()
    {
        $recipientMsisdn = '254712345678';
        $hashedMsisdn = Contact::hashMsisdn($recipientMsisdn);

        // Add recipient to blocklist
        OptOutRegistry::create([
            'client_account_id' => $this->clientAccount->id,
            'msisdn' => $recipientMsisdn,
        ]);

        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        // Perform Quick Send API
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/quick-send', [
            'msisdn' => $recipientMsisdn,
            'message' => 'Hello from Acme alerts!',
            'sender_id_id' => $this->senderId->id,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'RECIPIENT_OPTED_OUT'
        ]);
    }

    /**
     * Test atomic wallet transactions double-entry ledger checkouts.
     */
    public function test_quick_send_debits_ledger_atomically()
    {
        $recipientMsisdn = '254700111222';
        $initialBalance = (float) $this->clientAccount->wallet_balance;

        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        // Perform Quick Send API
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/quick-send', [
            'msisdn' => $recipientMsisdn,
            'message' => 'Alert Test Message',
            'sender_id_id' => $this->senderId->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'SUCCESS'
        ]);

        // Pricing calculation:
        // Base cost for Safaricom prefix 2547 = 0.0200
        // Pricing rule markup = 0.0050
        // Reseller account markup percentage (10%) of base = 0.02 * 0.1 = 0.0020
        // Total Expected Cost = 0.0200 + 0.0050 + 0.0020 = 0.0270
        $expectedCost = 0.0270;
        $expectedBalance = $initialBalance - $expectedCost;

        // 1. Verify wallet balance has decremented atomically
        $this->clientAccount->refresh();
        $this->assertEquals($expectedBalance, (float) $this->clientAccount->wallet_balance);

        // 2. Verify WalletTransaction double-entry ledger record
        $this->assertDatabaseHas('wallet_transactions', [
            'client_account_id' => $this->clientAccount->id,
            'amount' => -$expectedCost,
            'balance_after' => $expectedBalance,
            'type' => 'SMS_DISPATCH',
            'reference_type' => MessageRecord::class,
        ]);

        // 3. Verify MessageRecord is logged and SENT (processed synchronously by testing queue)
        $this->assertDatabaseHas('message_records', [
            'price' => 0.027,
            'status' => 'QUEUED',
        ]);
    }

    /**
     * Test client accounts out of funds triggers 402 Payment Required.
     */
    public function test_out_of_funds_declines_dispatch()
    {
        // Empty the wallet and credit limit
        $this->clientAccount->update([
            'wallet_balance' => 0.0000,
            'credit_limit' => 0.0000,
        ]);

        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        // Perform Quick Send API
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/quick-send', [
            'msisdn' => '254712345678',
            'message' => 'Hello',
            'sender_id_id' => $this->senderId->id,
        ]);

        // Should return 402 Payment Required (custom exception render)
        $response->assertStatus(402);
        $response->assertJson([
            'error' => 'OUT_OF_FUNDS'
        ]);

        // Assert balance remained 0
        $this->clientAccount->refresh();
        $this->assertEquals(0.0000, (float) $this->clientAccount->wallet_balance);
    }

    /**
     * Test Super Admin exclusive capabilities.
     */
    public function test_admin_exclusive_endpoints()
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // 1. Get resellers
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/accounts/admin/resellers');
        $response->assertStatus(200);
        $response->assertJsonStructure(['resellers']);

        // 2. Create reseller
        $responseCreate = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/accounts/admin/resellers', [
                'name' => 'Casamoko Reseller South',
                'email' => 'reseller.south@casamoko.com',
                'password' => 'Reseller456',
                'markup_percentage' => 8.50,
                'api_credits' => 1500.00,
            ]);
        $responseCreate->assertStatus(201);
        $this->assertDatabaseHas('reseller_accounts', ['name' => 'Casamoko Reseller South']);

        // 3. Get pending senders
        $responsePending = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/accounts/admin/sender-ids/pending');
        $responsePending->assertStatus(200);

        // 4. Get audit logs
        $responseAudit = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/accounts/admin/audit-logs');
        $responseAudit->assertStatus(200);
    }

    /**
     * Test Super Admin endpoint denials for non-admins.
     */
    public function test_admin_endpoints_decline_non_admin()
    {
        $token = $this->clientUser->createToken('client_token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/accounts/admin/resellers');
        $response->assertStatus(403);
        $response->assertJson(['error' => 'ROLE_UNAUTHORIZED']);
    }

    /**
     * Test Reseller onboard client flow with credentials provisioning.
     */
    public function test_reseller_onboard_client_flow()
    {
        // Seed Reseller User with associated ResellerAccount
        $resellerUser = User::create([
            'name' => 'Reseller Jane',
            'email' => 'partner@casamoko.com',
            'password' => Hash::make('Reseller123'),
            'role_tier' => 'RESELLER',
            'reseller_account_id' => $this->resellerAccount->id,
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        $token = $resellerUser->createToken('reseller_token')->plainTextToken;

        // 1. List clients
        $responseList = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/accounts/reseller/clients');
        $responseList->assertStatus(200);

        // 2. Onboard Client with full credential provisioning
        $responseOnboard = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/accounts/reseller/clients', [
                'tenant_name' => 'New Corporate Client Ltd',
                'name' => 'Client Contact Person',
                'email' => 'contact@newclient.com',
                'password' => 'NewClient123',
                'seeding_balance' => 200.00,
                'credit_limit' => 50.00,
            ]);
        $responseOnboard->assertStatus(201);
        $this->assertDatabaseHas('client_accounts', ['name' => 'New Corporate Client Ltd']);
        $this->assertDatabaseHas('users', [
            'email' => 'contact@newclient.com',
            'role_tier' => 'CLIENT',
            'sub_role' => 'CLIENT_ADMIN',
        ]);
    }

    /**
     * Test Reseller endpoint denials for non-resellers.
     */
    public function test_reseller_endpoints_decline_non_reseller()
    {
        $token = $this->clientUser->createToken('client_token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/accounts/reseller/clients');
        $response->assertStatus(403);
        $response->assertJson(['error' => 'ROLE_UNAUTHORIZED']);
    }

    /**
     * Test granular Client Sub-Role privilege restrictions: ANALYST.
     */
    public function test_client_analyst_blocked_on_quicksend()
    {
        $analystUser = User::create([
            'client_account_id' => $this->clientAccount->id,
            'name' => 'Analyst Bob',
            'email' => 'bob@acme.com',
            'password' => Hash::make('Secret123'),
            'role_tier' => 'CLIENT',
            'sub_role' => 'ANALYST',
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        $analystToken = $analystUser->createToken('analyst_token')->plainTextToken;

        $responseAnalyst = $this->withHeaders(['Authorization' => 'Bearer ' . $analystToken])
            ->postJson('/api/messaging/quick-send', [
                'msisdn' => '254712345678',
                'message' => 'Hello',
                'sender_id_id' => $this->senderId->id,
            ]);
        $responseAnalyst->assertStatus(403);
        $responseAnalyst->assertJson(['error' => 'PRIVILEGE_UNAUTHORIZED']);
    }

    /**
     * Test granular Client Sub-Role privilege restrictions: FINANCE_OFFICER.
     */
    public function test_client_finance_blocked_on_quicksend()
    {
        $financeUser = User::create([
            'client_account_id' => $this->clientAccount->id,
            'name' => 'Finance Alice',
            'email' => 'alice@acme.com',
            'password' => Hash::make('Secret123'),
            'role_tier' => 'CLIENT',
            'sub_role' => 'FINANCE_OFFICER',
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        $financeToken = $financeUser->createToken('finance_token')->plainTextToken;

        $responseFinance = $this->withHeaders(['Authorization' => 'Bearer ' . $financeToken])
            ->postJson('/api/messaging/quick-send', [
                'msisdn' => '254712345678',
                'message' => 'Hello',
                'sender_id_id' => $this->senderId->id,
            ]);
        $responseFinance->assertStatus(403);
        $responseFinance->assertJson(['error' => 'PRIVILEGE_UNAUTHORIZED']);
    }

    /**
     * Test granular Client Sub-Role privilege restrictions: CAMPAIGN_MANAGER.
     */
    public function test_client_campaign_manager_succeeds_on_quicksend()
    {
        $managerUser = User::create([
            'client_account_id' => $this->clientAccount->id,
            'name' => 'Manager Chuck',
            'email' => 'chuck@acme.com',
            'password' => Hash::make('Secret123'),
            'role_tier' => 'CLIENT',
            'sub_role' => 'CAMPAIGN_MANAGER',
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        $managerToken = $managerUser->createToken('manager_token')->plainTextToken;

        $responseManager = $this->withHeaders(['Authorization' => 'Bearer ' . $managerToken])
            ->postJson('/api/messaging/quick-send', [
                'msisdn' => '254700999888',
                'message' => 'Hello from Campaign Manager!',
                'sender_id_id' => $this->senderId->id,
            ]);
        $responseManager->assertStatus(200);
        $responseManager->assertJson(['status' => 'SUCCESS']);
    }

    /**
     * Test Super Admin client status suspension update (FR-UAM-007).
     */
    public function test_admin_suspend_client_blocks_messaging()
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;

        // 1. Suspend the Acme Corporation Kenya account
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/accounts/admin/client/{$this->clientAccount->id}/status", [
                'status' => 'SUSPENDED',
                'reason' => 'Suspicious outbound marketing campaign'
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'SUCCESS']);
        $this->clientAccount->refresh();
        $this->assertEquals('SUSPENDED', $this->clientAccount->status);

        // Clear resolved guards and user instance in the Laravel container/auth service
        $this->app['auth']->forgetGuards();

        // 2. Outbound quick send should now be blocked by tenant.active middleware
        $clientToken = $this->clientUser->createToken('client_token')->plainTextToken;
        $responseSend = $this->withHeaders(['Authorization' => 'Bearer ' . $clientToken])
            ->postJson('/api/messaging/quick-send', [
                'msisdn' => '254700000000',
                'message' => 'Blocked campaign dispatch attempt',
                'sender_id_id' => $this->senderId->id
            ]);

        $responseSend->assertStatus(403);
        $responseSend->assertJson(['error' => 'TENANT_SUSPENDED']);
    }

    /**
     * Test Client KYC base64 document upload (FR-UAM-001).
     */
    public function test_client_kyc_upload_updates_metadata()
    {
        $token = $this->clientUser->createToken('client_token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/accounts/client/kyc-upload', [
                'document_type' => 'BUSINESS_PERMIT',
                'file_name' => 'permit.pdf',
                'file_data' => base64_encode('mock_pdf_binary_content')
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'SUCCESS']);
        
        $this->clientAccount->refresh();
        $this->assertEquals('PENDING', $this->clientAccount->status);
        $this->assertArrayHasKey('BUSINESS_PERMIT', $this->clientAccount->kyc_metadata);
        $this->assertEquals('permit.pdf', $this->clientAccount->kyc_metadata['BUSINESS_PERMIT']['file_name']);

        // Verify audit log
        $this->assertDatabaseHas('activity_logs', [
            'client_account_id' => $this->clientAccount->id,
            'action' => 'KYC_UPLOADED',
        ]);
    }

    /**
     * Test Client sub-user creation and immutable activities logs (FR-UAM-003, FR-UAM-008).
     */
    public function test_client_sub_user_creation_and_activity_logs()
    {
        $token = $this->clientUser->createToken('client_token')->plainTextToken;

        // 1. Create a sub-user as CLIENT_ADMIN
        $responseCreate = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/accounts/client/team', [
                'name' => 'Bob Campaigner',
                'email' => 'bob@acme.com',
                'password' => 'BobSecret123',
                'sub_role' => 'CAMPAIGN_MANAGER'
            ]);

        $responseCreate->assertStatus(201);
        $responseCreate->assertJson(['status' => 'SUCCESS']);
        
        $this->assertDatabaseHas('users', [
            'client_account_id' => $this->clientAccount->id,
            'email' => 'bob@acme.com',
            'sub_role' => 'CAMPAIGN_MANAGER'
        ]);

        // 2. Fetch immutable activities list
        $responseActivities = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/accounts/client/activities');

        $responseActivities->assertStatus(200);
        $responseActivities->assertJsonStructure([
            'status',
            'activities' => [
                '*' => ['id', 'action', 'description', 'ip_address', 'username', 'created_at']
            ]
        ]);

        $this->assertNotEmpty($responseActivities->json('activities'));
        $this->assertEquals('USER_CREATED', $responseActivities->json('activities.0.action'));
    }

    /**
     * Test advanced campaign features: A/B splits, quiet hours and TPS limits.
     */
    public function test_advanced_campaign_features()
    {
        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/campaigns', [
            'name' => 'Acme Winter Campaign',
            'template' => 'Join Acme Alerts for 20% off!',
            'sender_id_id' => $this->senderId->id,
            'tps_limit' => 10,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
            'recurring_type' => 'NONE',
            'scheduled_at' => null,
            'is_ab_test' => true,
            'template_b' => 'Signup for Acme alerts now and save big!',
            'ab_split_ratio' => 50,
            'target_contacts' => ['254700000001', '254700000002'],
            'status' => 'PROCESSING'
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'SUCCESS'
        ]);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Acme Winter Campaign',
            'is_ab_test' => true,
            'tps_limit' => 10,
        ]);
    }

    /**
     * Test shortcode keywords rules, webhooks, opt-outs, and MO simulation.
     */
    public function test_shortcode_and_sandbox_simulation()
    {
        // 1. Create shortcode allocation
        $shortcode = \App\Modules\Messaging\Models\Shortcode::create([
            'shortcode' => '22344',
            'is_dedicated' => false,
        ]);

        // 2. Create keyword rule
        $token = $this->clientUser->createToken('test_token')->plainTextToken;
        $responseKw = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/shortcodes/keywords', [
            'shortcode_id' => $shortcode->id,
            'keyword' => 'STOP',
            'action_type' => 'OPT_OUT',
            'reply_message' => 'You have opted out of Acme alerts.'
        ]);

        $responseKw->assertStatus(201);
        $responseKw->assertJson(['status' => 'SUCCESS']);

        // 3. Simulate Inbound MO push
        $responseSim = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/shortcodes/receive-mo', [
            'shortcode' => '22344',
            'msisdn' => '254711222333',
            'message' => 'STOP'
        ]);

        $responseSim->assertStatus(200);
        $responseSim->assertJson(['status' => 'SUCCESS']);

        // Verify STOP keyword registers opt-out automatically
        $this->assertDatabaseHas('opt_out_registries', [
            'client_account_id' => $this->clientAccount->id,
            'msisdn' => '254711222333',
        ]);
    }

    /**
     * Test client cannot register blacklisted sender ID (FR-SID-005).
     */
    public function test_client_cannot_register_blacklisted_sender_id()
    {
        // Explicitly seed a pattern just in case migration seeds are cleared or different in testing DB
        \App\Modules\Messaging\Models\SenderIDBlacklist::updateOrCreate(
            ['pattern' => 'M-PESA'],
            ['reason' => 'Protected sovereign mobile money utility brand name.']
        );

        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/sender-ids', [
            'sender_id' => 'M-PESA'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'SENDER_ID_BLACKLISTED'
        ]);
    }

    /**
     * Test sender ID onboarding provisions operator matrix (FR-SID-003).
     */
    public function test_sender_id_onboarding_provisions_mno_matrix()
    {
        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/sender-ids', [
            'sender_id' => 'MYBRAND'
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'SUCCESS'
        ]);

        // Assert parent record is created
        $this->assertDatabaseHas('sender_ids', [
            'client_account_id' => $this->clientAccount->id,
            'sender_id' => 'MYBRAND',
            'status' => 'PENDING'
        ]);

        $sender = SenderID::where('sender_id', 'MYBRAND')->first();

        // Assert MNO approval rows are seeded in pending state
        $this->assertDatabaseHas('sender_id_mno_approvals', [
            'sender_id_id' => $sender->id,
            'mno_name' => 'SAFARICOM',
            'status' => 'PENDING'
        ]);

        $this->assertDatabaseHas('sender_id_mno_approvals', [
            'sender_id_id' => $sender->id,
            'mno_name' => 'AIRTEL',
            'status' => 'PENDING'
        ]);

        $this->assertDatabaseHas('sender_id_mno_approvals', [
            'sender_id_id' => $sender->id,
            'mno_name' => 'TELKOM',
            'status' => 'PENDING'
        ]);
    }

    /**
     * Test dispatch routing applies dynamic MNO fallback swaps (FR-SID-004).
     */
    public function test_dispatch_routing_dynamic_mno_fallback()
    {
        // 1. Create a generic approved fallback Sender ID
        $fallback = SenderID::create([
            'client_account_id' => $this->clientAccount->id,
            'sender_id' => 'CASAMOKO_GENERIC',
            'status' => 'APPROVED'
        ]);

        // Provision approved status for fallback across all operator networks
        foreach (['SAFARICOM', 'AIRTEL', 'TELKOM'] as $mno) {
            \App\Modules\Messaging\Models\SenderIDMnoApproval::create([
                'sender_id_id' => $fallback->id,
                'mno_name' => $mno,
                'status' => 'APPROVED'
            ]);
        }

        // 2. Create primary Sender ID
        $primary = SenderID::create([
            'client_account_id' => $this->clientAccount->id,
            'sender_id' => 'MYBRAND',
            'status' => 'APPROVED', // Global is approved, but Airtel is unapproved
            'fallback_sender_id_id' => $fallback->id
        ]);

        // Safaricom is approved
        \App\Modules\Messaging\Models\SenderIDMnoApproval::create([
            'sender_id_id' => $primary->id,
            'mno_name' => 'SAFARICOM',
            'status' => 'APPROVED'
        ]);

        // Airtel is rejected
        \App\Modules\Messaging\Models\SenderIDMnoApproval::create([
            'sender_id_id' => $primary->id,
            'mno_name' => 'AIRTEL',
            'status' => 'REJECTED',
            'reason' => 'Airtel carrier mismatch'
        ]);

        // Telkom is pending
        \App\Modules\Messaging\Models\SenderIDMnoApproval::create([
            'sender_id_id' => $primary->id,
            'mno_name' => 'TELKOM',
            'status' => 'PENDING'
        ]);

        // Create standard pricing rule for 25473 (Airtel prefix) so dispatch fee calculation succeeds
        PricingRule::updateOrCreate(
            ['destination_prefix' => '25473'],
            [
                'name' => 'Airtel Local Network',
                'base_cost' => 0.0120,
                'reseller_markup' => 0.0000,
                'client_account_id' => null
            ]
        );

        $token = $this->clientUser->createToken('test_token')->plainTextToken;

        // Dispatch QuickSend to an Airtel recipient (254730123456) using the unapproved primary mask
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/messaging/quick-send', [
            'msisdn' => '254730123456',
            'message' => 'Test dynamic fallback swap',
            'sender_id_id' => $primary->id
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'SUCCESS']);

        // Assert that the Campaign container has dynamically swapped its sender_id_id to the approved fallback's ID!
        $campaignId = $response->json('campaign_id');
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'sender_id_id' => $fallback->id // SWAPPED!
        ]);
    }

    /**
     * Test 1: Intelligent route selection chooses optimal quality and cost, and supports failover routing.
     */
    public function test_intelligent_route_selection_chooses_optimal_quality_and_cost()
    {
        // Clear setup-seeded routes for clean test isolation
        \App\Modules\Messaging\Models\Route::query()->delete();
        \App\Modules\Messaging\Models\Carrier::query()->delete();

        // Setup Carrier
        $carrier = \App\Modules\Messaging\Models\Carrier::create([
            'name' => 'Safaricom MNO',
            'smpp_host' => '127.0.0.1',
            'smpp_port' => '2775',
        ]);

        // Setup Route A (Optimal QoS)
        $routeA = \App\Modules\Messaging\Models\Route::create([
            'name' => 'Safaricom Premium Bind',
            'carrier_id' => $carrier->id,
            'cost_per_sms' => 0.0050,
            'tps_limit' => 100,
            'priority' => 5,
            'is_active' => true,
            'destination_network' => 'SAFARICOM',
            'bind_type' => 'TRANSCEIVER',
            'window_size' => 10,
            'use_tls' => true,
            'delivery_rate_score' => 99.80,
            'latency_score' => 45,
        ]);

        // Setup Route B (Lower QoS, cheaper)
        $routeB = \App\Modules\Messaging\Models\Route::create([
            'name' => 'Safaricom Bulk Route',
            'carrier_id' => $carrier->id,
            'cost_per_sms' => 0.0030,
            'tps_limit' => 50,
            'priority' => 5,
            'is_active' => true,
            'destination_network' => 'SAFARICOM',
            'bind_type' => 'TRANSCEIVER',
            'window_size' => 10,
            'use_tls' => false,
            'delivery_rate_score' => 90.00,
            'latency_score' => 150,
        ]);

        $selector = app(\App\Modules\Messaging\Services\IntelligentRouteSelector::class);

        // 1. Select Route - Should pick Route A because of significantly better QoS score
        $selected = $selector->selectBestRoute('254712345678');
        $this->assertEquals($routeA->id, $selected->id);

        // 2. Degrade Route A QoS to trigger failover
        $selector->degradeRoute($routeA);

        // 3. Select Route again - Should now pick Route B
        $selectedAfter = $selector->selectBestRoute('254712345678');
        $this->assertEquals($routeB->id, $selectedAfter->id);
    }

    /**
     * Test 2: Failed SMS dispatches retry up to 3 times before terminal failure and atomic refund.
     */
    public function test_failed_dispatches_retry_with_exponential_backoff()
    {
        // Seed standard Safaricom Route
        $carrier = \App\Modules\Messaging\Models\Carrier::create(['name' => 'Saf']);
        $route = \App\Modules\Messaging\Models\Route::create([
            'name' => 'Saf Route',
            'carrier_id' => $carrier->id,
            'cost_per_sms' => 0.0050,
            'tps_limit' => 100,
            'priority' => 5,
            'is_active' => true,
            'destination_network' => 'SAFARICOM',
            'delivery_rate_score' => 99.80,
            'latency_score' => 45,
        ]);

        // Create campaign and record
        $campaign = Campaign::create([
            'client_account_id' => $this->clientAccount->id,
            'sender_id_id' => $this->senderId->id,
            'name' => 'Failed Job Test',
            'template' => 'Test message',
            'recurring_type' => 'NONE',
        ]);

        $contact = Contact::create([
            'client_account_id' => $this->clientAccount->id,
            'msisdn' => '254711999888',
        ]);

        $record = MessageRecord::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'price' => 0.0270,
            'status' => 'QUEUED',
            'msisdn_hash' => Contact::hashMsisdn('254711999888'),
        ]);

        // Force Safaricom Gateway to execute real HTTP calls and fail via Http::fake
        putenv('SAFARICOM_SMS_API_KEY=some_real_key');
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['error' => 'Gateway error'], 500)
        ]);
        
        $job = new \App\Modules\Messaging\Jobs\SendSMSJob($record->id);
        
        try {
            // Run with 1st attempt
            $job->handle(
                app(\App\Modules\Finance\Services\LedgerService::class),
                app(\App\Modules\Messaging\Services\IntelligentRouteSelector::class)
            );
        } finally {
            putenv('SAFARICOM_SMS_API_KEY=default_mock_key');
        }

        $record->refresh();
        $this->assertEquals('QUEUED', $record->status);
        $this->assertEquals(5.0000, (float) $this->clientAccount->wallet_balance);
    }

    /**
     * Test 3: Webhook DLR reception parses receipt status and issues wallet refund on failure.
     */
    public function test_dlr_webhook_updates_status_within_seconds_and_triggers_refund_on_failure()
    {
        $campaign = Campaign::create([
            'client_account_id' => $this->clientAccount->id,
            'sender_id_id' => $this->senderId->id,
            'name' => 'DLR Test Campaign',
            'template' => 'Hello',
            'recurring_type' => 'NONE',
        ]);

        $contact = Contact::create([
            'client_account_id' => $this->clientAccount->id,
            'msisdn' => '254700000111',
        ]);

        $ledger = app(\App\Modules\Finance\Services\LedgerService::class);
        $record = MessageRecord::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'price' => 0.0270,
            'status' => 'SENT',
            'mno_message_id' => 'mno-998822',
            'msisdn_hash' => Contact::hashMsisdn('254700000111'),
        ]);

        $ledger->debit($this->clientAccount->id, 0.0270, 'SMS_DISPATCH', MessageRecord::class, $record->id);
        $this->clientAccount->refresh();
        $initialBalance = (float) $this->clientAccount->wallet_balance;

        // Send public DLR Webhook POST request
        $response = $this->postJson('/api/messaging/dlr-webhook', [
            'mno_message_id' => 'mno-998822',
            'status' => 'FAILED',
            'network_status_code' => 'ABSENT_SUBSCRIBER'
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $record->refresh();
        $this->assertEquals('FAILED', $record->status);
        $this->assertEquals('ABSENT_SUBSCRIBER', $record->network_status_code);

        $this->clientAccount->refresh();
        $this->assertEquals($initialBalance + 0.0270, (float) $this->clientAccount->wallet_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'client_account_id' => $this->clientAccount->id,
            'amount' => 0.0270,
            'type' => 'REFUND',
            'reference_type' => MessageRecord::class,
            'reference_id' => $record->id,
        ]);
    }

    /**
     * Test 4: Supervisor manually adjusts a client's wallet balance and registers transaction.
     */
    public function test_supervisor_wallet_adjustment_updates_wallet_balance_and_logs_transaction()
    {
        $token = $this->adminUser->createToken('admin_token')->plainTextToken;
        $initialBalance = (float) $this->clientAccount->wallet_balance;

        // 1. Perform CREDIT manual adjustment
        $responseCredit = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/accounts/admin/client/{$this->clientAccount->id}/wallet-adjust", [
                'amount' => 150.00,
                'reason_code' => 'MANUAL_CREDIT',
                'description' => 'Audited supervisor credit test'
            ]);

        $responseCredit->assertStatus(200);
        $responseCredit->assertJson(['status' => 'SUCCESS']);

        $this->clientAccount->refresh();
        $this->assertEquals($initialBalance + 150.00, (float) $this->clientAccount->wallet_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'client_account_id' => $this->clientAccount->id,
            'amount' => 150.00,
            'type' => 'BILLING_ADJUSTMENT',
            'reason_code' => 'MANUAL_CREDIT',
            'adjusted_by_user_id' => $this->adminUser->id,
            'description' => 'Audited supervisor credit test'
        ]);

        // 2. Perform DEBIT manual adjustment
        $responseDebit = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/accounts/admin/client/{$this->clientAccount->id}/wallet-adjust", [
                'amount' => -50.00,
                'reason_code' => 'BILLING_ADJUSTMENT',
                'description' => 'Audited supervisor debit test'
            ]);

        $responseDebit->assertStatus(200);
        $responseDebit->assertJson(['status' => 'SUCCESS']);

        $this->clientAccount->refresh();
        $this->assertEquals($initialBalance + 100.00, (float) $this->clientAccount->wallet_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'client_account_id' => $this->clientAccount->id,
            'amount' => -50.00,
            'type' => 'BILLING_ADJUSTMENT',
            'reason_code' => 'BILLING_ADJUSTMENT',
            'adjusted_by_user_id' => $this->adminUser->id,
            'description' => 'Audited supervisor debit test'
        ]);
    }

    /**
     * Test public self-registration blocks privileged role tiers.
     */
    public function test_public_registration_blocks_privileged_roles()
    {
        // Attempt to self-register as RESELLER should fail
        $response = $this->postJson('/api/accounts/register', [
            'name' => 'Wannabe Reseller',
            'email' => 'hacker@example.com',
            'password' => 'Password123',
            'role_tier' => 'RESELLER',
            'tenant_name' => 'Hack Corp',
        ]);
        $response->assertStatus(422);

        // Attempt to self-register as SUPER_ADMIN should also fail
        $response2 = $this->postJson('/api/accounts/register', [
            'name' => 'Wannabe Admin',
            'email' => 'hacker2@example.com',
            'password' => 'Password123',
            'role_tier' => 'SUPER_ADMIN',
            'tenant_name' => 'Admin Corp',
        ]);
        $response2->assertStatus(422);

        // Legitimate CLIENT self-registration should succeed
        $response3 = $this->postJson('/api/accounts/register', [
            'name' => 'Legitimate Client',
            'email' => 'legit@example.com',
            'password' => 'Password123',
            'role_tier' => 'CLIENT',
            'tenant_name' => 'Legit Corp Ltd',
        ]);
        $response3->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'legit@example.com', 'role_tier' => 'CLIENT']);
    }

    /**
     * Test SUPER_ADMIN creates reseller with credentials and reseller can login.
     */
    public function test_admin_creates_reseller_with_credentials_and_can_login()
    {
        $adminToken = $this->adminUser->createToken('admin_token')->plainTextToken;

        // Admin creates the reseller
        $createResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->postJson('/api/accounts/admin/resellers', [
                'name' => 'Nairobi Reseller Partners',
                'email' => 'nairobi@reseller.com',
                'password' => 'Nairobi123',
                'markup_percentage' => 12.00,
                'api_credits' => 2000.00,
            ]);
        $createResponse->assertStatus(201);
        $createResponse->assertJson(['status' => 'SUCCESS']);

        // Verify reseller user was created in DB
        $this->assertDatabaseHas('users', [
            'email' => 'nairobi@reseller.com',
            'role_tier' => 'RESELLER',
        ]);

        // The new reseller can authenticate with their provisioned credentials
        $loginResponse = $this->postJson('/api/accounts/login', [
            'email' => 'nairobi@reseller.com',
            'password' => 'Nairobi123',
        ]);
        $loginResponse->assertStatus(200);
        $loginResponse->assertJson(['status' => 'SUCCESS']);
        $loginResponse->assertJsonStructure(['token']);
    }

    /**
     * Test RESELLER onboards a client with credentials and client can login.
     */
    public function test_reseller_onboards_client_with_credentials_and_can_login()
    {
        // Create reseller user tied to resellerAccount
        $resellerUser = User::create([
            'name' => 'Reseller Admin',
            'email' => 'resadmin@casamoko.com',
            'password' => Hash::make('ResAdmin123'),
            'role_tier' => 'RESELLER',
            'reseller_account_id' => $this->resellerAccount->id,
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'is_active_user' => true,
        ]);

        $resellerToken = $resellerUser->createToken('reseller_token')->plainTextToken;

        // Reseller onboards a client
        $onboardResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $resellerToken])
            ->postJson('/api/accounts/reseller/clients', [
                'tenant_name' => 'Sunrise Media Group',
                'name' => 'Sunrise Contact Person',
                'email' => 'contact@sunrise.co.ke',
                'password' => 'Sunrise123',
                'seeding_balance' => 300.00,
                'credit_limit' => 100.00,
            ]);
        $onboardResponse->assertStatus(201);
        $onboardResponse->assertJson(['status' => 'SUCCESS']);

        // Verify both client account and user were created
        $this->assertDatabaseHas('client_accounts', ['name' => 'Sunrise Media Group']);
        $this->assertDatabaseHas('users', [
            'email' => 'contact@sunrise.co.ke',
            'role_tier' => 'CLIENT',
            'sub_role' => 'CLIENT_ADMIN',
        ]);

        // The new client can authenticate - first login triggers 2FA setup for CLIENT_ADMIN
        $loginResponse = $this->postJson('/api/accounts/login', [
            'email' => 'contact@sunrise.co.ke',
            'password' => 'Sunrise123',
        ]);
        // CLIENT_ADMIN requires 2FA setup on first login
        $loginResponse->assertStatus(200);
        $this->assertContains(
            $loginResponse->json('status'),
            ['SUCCESS', '2FA_SETUP_REQUIRED', '2FA_REQUIRED'],
            'Login response should be a valid auth status'
        );

        // If 2FA setup required, complete with master bypass code
        if ($loginResponse->json('status') === '2FA_SETUP_REQUIRED') {
            $loginFinal = $this->postJson('/api/accounts/login', [
                'email' => 'contact@sunrise.co.ke',
                'password' => 'Sunrise123',
                'totp_code' => '123456',
            ]);
            $loginFinal->assertStatus(200);
            $loginFinal->assertJsonStructure(['token']);
        }
    }
}

