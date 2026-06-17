<?php

namespace App\Modules\Finance\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        return response()->json([
            'error' => 'OUT_OF_FUNDS',
            'message' => $this->getMessage() ?: 'Insufficient wallet balance to perform this operation.'
        ], 402); // 402 Payment Required
    }
}
