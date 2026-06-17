<?php

namespace App\Modules\Finance\Services;

use App\Modules\Accounts\Models\ClientAccount;
use App\Modules\Finance\Models\WalletTransaction;
use App\Modules\Finance\Exceptions\InsufficientFundsException;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Deducts funds atomically from a Client's wallet balance.
     * Enforces row locking (SELECT FOR UPDATE) to prevent race conditions.
     *
     * @throws InsufficientFundsException
     */
    public function debit(
        int $clientAccountId,
        float $amount,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($clientAccountId, $amount, $type, $referenceType, $referenceId, $description) {
            // Lock the client account row for update
            $client = ClientAccount::where('id', $clientAccountId)
                ->lockForUpdate()
                ->first();

            if (!$client) {
                throw new \InvalidArgumentException("Client account ID {$clientAccountId} does not exist.");
            }

            // Verify sufficient funds (wallet balance + credit limit)
            if (!$client->hasSufficientFunds($amount)) {
                throw new InsufficientFundsException(
                    sprintf(
                        "Transaction declined due to insufficient funds. Balance: %s, Credit Limit: %s. Required: %s.",
                        number_format($client->wallet_balance, 2),
                        number_format($client->credit_limit, 2),
                        number_format($amount, 2)
                    )
                );
            }

            // Deduct from balance
            $client->wallet_balance -= $amount;
            $client->save();

            // Log ledger entry
            return WalletTransaction::create([
                'client_account_id' => $client->id,
                'amount' => -$amount, // negative for debits
                'balance_after' => $client->wallet_balance,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Credits funds atomically to a Client's wallet balance.
     * Enforces row locking (SELECT FOR UPDATE) to prevent concurrency anomalies.
     */
    public function credit(
        int $clientAccountId,
        float $amount,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($clientAccountId, $amount, $type, $referenceType, $referenceId, $description) {
            // Lock the client account row for update
            $client = ClientAccount::where('id', $clientAccountId)
                ->lockForUpdate()
                ->first();

            if (!$client) {
                throw new \InvalidArgumentException("Client account ID {$clientAccountId} does not exist.");
            }

            // Add to balance
            $client->wallet_balance += $amount;
            $client->save();

            // Log ledger entry
            return WalletTransaction::create([
                'client_account_id' => $client->id,
                'amount' => $amount, // positive for credits
                'balance_after' => $client->wallet_balance,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Performs a manual wallet adjustment (credit or debit) by an administrator.
     */
    public function adjust(
        int $clientAccountId,
        float $amount,
        string $reasonCode,
        int $adjustedByUserId,
        ?string $description = null
    ): WalletTransaction {
        if ($amount === 0.0) {
            throw new \InvalidArgumentException('Adjustment amount cannot be zero.');
        }

        return DB::transaction(function () use ($clientAccountId, $amount, $reasonCode, $adjustedByUserId, $description) {
            $client = ClientAccount::where('id', $clientAccountId)
                ->lockForUpdate()
                ->first();

            if (!$client) {
                throw new \InvalidArgumentException("Client account ID {$clientAccountId} does not exist.");
            }

            if ($amount < 0) {
                $absAmount = abs($amount);
                if (!$client->hasSufficientFunds($absAmount)) {
                    throw new InsufficientFundsException(
                        sprintf(
                            "Manual debit declined. Insufficient funds. Balance: %s, Credit Limit: %s. Required: %s.",
                            number_format($client->wallet_balance, 2),
                            number_format($client->credit_limit, 2),
                            number_format($absAmount, 2)
                        )
                    );
                }
            }

            $client->wallet_balance += $amount;
            $client->save();

            return WalletTransaction::create([
                'client_account_id' => $client->id,
                'amount' => $amount,
                'balance_after' => $client->wallet_balance,
                'type' => 'BILLING_ADJUSTMENT',
                'reason_code' => $reasonCode,
                'adjusted_by_user_id' => $adjustedByUserId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }
}

