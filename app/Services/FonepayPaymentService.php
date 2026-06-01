<?php

namespace App\Services;

use RuntimeException;

class FonepayPaymentService
{
    /**
     * Initiate payment via Fonepay.
     * 
     * @throws RuntimeException
     */
    public function initiatePayment(array $data): array
    {
        throw new RuntimeException("Fonepay payment integration is not implemented yet.");
    }

    /**
     * Verify payment status via Fonepay.
     * 
     * @throws RuntimeException
     */
    public function verifyPayment(string $prn): array
    {
        throw new RuntimeException("Fonepay payment integration is not implemented yet.");
    }
}
