<?php

namespace App\Services;

use RuntimeException;

class KhaltiPaymentService
{
    /**
     * Initiate payment via Khalti.
     * 
     * @throws RuntimeException
     */
    public function initiatePayment(array $data): array
    {
        throw new RuntimeException("Khalti payment integration is not implemented yet.");
    }

    /**
     * Verify payment status via Khalti.
     * 
     * @throws RuntimeException
     */
    public function verifyPayment(string $pidx): array
    {
        throw new RuntimeException("Khalti payment integration is not implemented yet.");
    }
}
