<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Create a manual cash, bank, qr, or credit payment.
     */
    public function createManualPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $invoiceId = $data['invoice_id'] ?? null;
            $orderId = $data['order_id'] ?? null;

            if (!$invoiceId && !$orderId) {
                throw ValidationException::withMessages([
                    'invoice_id' => ['Either invoice_id or order_id is required.']
                ]);
            }

            // Resolve target records
            $invoice = $invoiceId ? Invoice::findOrFail($invoiceId) : null;
            $order = $orderId ? Order::findOrFail($orderId) : null;

            // If only invoice exists but it is linked to an order, resolve order
            if ($invoice && !$order && $invoice->order_id) {
                $order = Order::find($invoice->order_id);
            }
            // If only order exists but it has an active invoice, resolve invoice
            if ($order && !$invoice) {
                $invoice = Invoice::where('order_id', $order->id)
                    ->whereIn('status', ['draft', 'issued', 'partially_paid', 'unpaid'])
                    ->first();
            }

            $gateway = $data['gateway'];
            $amount = (float) $data['amount'];
            $transactionId = $data['transaction_id'] ?? null;

            if ($amount <= 0.00) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount must be greater than zero.']
                ]);
            }

            // 1. Prevent duplicate transaction IDs per gateway/tenant context
            if ($transactionId) {
                $duplicate = Payment::where('gateway', $gateway)
                    ->where('transaction_id', $transactionId)
                    ->first();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'transaction_id' => ['Duplicate transaction ID detected for this payment gateway.']
                    ]);
                }
            }

            // 2. Validate outstanding due terms to prevent overpayment (unless gateway is credit)
            $outstandingDue = 0.00;
            if ($invoice) {
                $outstandingDue = (float) $invoice->due_amount;
            } elseif ($order) {
                $outstandingDue = (float) $order->due_amount;
            }

            if ($amount > $outstandingDue && $gateway !== 'credit') {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount exceeds the outstanding due balance.']
                ]);
            }

            // 3. Create successful payment record
            $payment = new Payment();
            $payment->invoice_id = $invoice?->id;
            $payment->order_id = $order?->id;
            $payment->customer_id = $invoice?->customer_id ?? $order?->customer_id ?? $data['customer_id'] ?? null;
            $payment->gateway = $gateway;
            $payment->amount = $amount;
            $payment->transaction_id = $transactionId;
            $payment->payment_date = $data['payment_date'] ?? now();
            $payment->status = 'successful';
            $payment->notes = $data['notes'] ?? null;
            $payment->save();

            // 4. Update targeting ledgers
            $this->applyPaymentToTargets($invoice, $order, $amount);

            return $payment;
        });
    }

    /**
     * Process gateway-confirmed payment transaction completion.
     */
    public function completeGatewayPayment(Payment $payment, string $refId, array $gatewayResponse): Payment
    {
        return DB::transaction(function () use ($payment, $refId, $gatewayResponse) {
            // Prevent duplicate applications
            if ($payment->status === 'successful') {
                return $payment;
            }

            $payment->status = 'successful';
            $payment->transaction_id = $refId;
            $payment->gateway_response = $gatewayResponse;
            $payment->save();

            // Resolve linked records
            $invoice = $payment->invoice_id ? Invoice::find($payment->invoice_id) : null;
            $order = $payment->order_id ? Order::find($payment->order_id) : null;

            if ($invoice && !$order && $invoice->order_id) {
                $order = Order::find($invoice->order_id);
            }
            if ($order && !$invoice) {
                $invoice = Invoice::where('order_id', $order->id)
                    ->whereIn('status', ['draft', 'issued', 'partially_paid'])
                    ->first();
            }

            // Apply transaction amount
            $this->applyPaymentToTargets($invoice, $order, (float)$payment->amount);

            return $payment;
        });
    }

    /**
     * Mark gateway-declined payment transaction as failed.
     */
    public function failGatewayPayment(Payment $payment, array $gatewayResponse): Payment
    {
        $payment->status = 'failed';
        $payment->gateway_response = $gatewayResponse;
        $payment->save();

        return $payment;
    }

    /**
     * Helper to synchronize amount balances on invoices and orders.
     */
    protected function applyPaymentToTargets(?Invoice $invoice, ?Order $order, float $amount): void
    {
        if ($invoice) {
            $invoice->paid_amount = round((float)$invoice->paid_amount + $amount, 2);
            $invoice->due_amount = round(max(0.00, (float)$invoice->total - $invoice->paid_amount), 2);
            
            if ($invoice->due_amount <= 0.00) {
                $invoice->status = 'paid';
            } else {
                $invoice->status = 'partially_paid';
            }
            $invoice->save();
        }

        if ($order) {
            $order->paid_amount = round((float)$order->paid_amount + $amount, 2);
            $order->due_amount = round(max(0.00, (float)$order->total - $order->paid_amount), 2);
            
            if ($order->due_amount <= 0.00) {
                $order->payment_status = 'paid';
            } else {
                $order->payment_status = 'partially_paid';
            }
            $order->save();
        }
    }
}
