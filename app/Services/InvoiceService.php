<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    protected PdfService $pdfService;

    public function __construct(PdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Create a manual invoice.
     */
    public function createManualInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = new Invoice();
            $invoice->customer_id = $data['customer_id'] ?? null;
            $invoice->order_id = $data['order_id'] ?? null;
            $invoice->invoice_number = $this->generateInvoiceNumber();
            $invoice->status = 'draft';
            $invoice->invoice_date = $data['invoice_date'] ?? now()->toDateString();
            $invoice->due_date = $data['due_date'] ?? null;
            $invoice->notes = $data['notes'] ?? null;
            $invoice->service_charge = $data['service_charge_amount'] ?? 0;
            
            // Empty defaults
            $invoice->subtotal = 0;
            $invoice->discount = 0;
            $invoice->taxable_amount = 0;
            $invoice->vat_amount = 0;
            $invoice->total = 0;
            $invoice->paid_amount = 0;
            $invoice->due_amount = 0;
            $invoice->save();

            $subtotal = 0;
            $totalItemDiscounts = 0;
            $invoiceItems = [];

            foreach ($data['items'] as $itemData) {
                $item = new InvoiceItem();
                $item->invoice_id = $invoice->id;
                $item->menu_item_id = $itemData['menu_item_id'] ?? null;
                $item->product_id = $itemData['product_id'] ?? null;
                $item->service_id = $itemData['service_id'] ?? null;
                $item->quantity = $itemData['quantity'];

                // Safe override catalog item unit prices
                $resolvedName = null;
                $resolvedPrice = null;

                if ($item->menu_item_id) {
                    $menuItem = MenuItem::findOrFail($item->menu_item_id);
                    $resolvedName = $menuItem->name;
                    $resolvedPrice = $menuItem->price;
                } elseif ($item->product_id) {
                    $product = Product::findOrFail($item->product_id);
                    $resolvedName = $product->name;
                    $resolvedPrice = $product->price;
                } elseif ($item->service_id) {
                    $svc = Service::findOrFail($item->service_id);
                    $resolvedName = $svc->name;
                    $resolvedPrice = $svc->price;
                }

                $item->name = $resolvedName ?? $itemData['name'] ?? 'Custom Item';
                $unitPrice = $resolvedPrice ?? $itemData['unit_price'] ?? 0;
                $item->unit_price = $unitPrice;

                $itemSubtotal = $item->quantity * $unitPrice;
                $itemDiscount = $itemData['discount_amount'] ?? 0;

                if ($itemDiscount > $itemSubtotal) {
                    $itemDiscount = $itemSubtotal;
                }

                $item->discount_amount = $itemDiscount;
                $item->total_amount = $itemSubtotal - $itemDiscount;

                $subtotal += $itemSubtotal;
                $totalItemDiscounts += $itemDiscount;

                $invoiceItems[] = $item;
            }

            // Calculations
            $invoiceLevelDiscount = $data['discount_amount'] ?? 0;
            $totalDiscount = $totalItemDiscounts + $invoiceLevelDiscount;

            if ($totalDiscount > $subtotal) {
                $totalDiscount = $subtotal;
            }

            $taxableAmount = $subtotal - $totalDiscount;

            // VAT calculation
            $vatEnabledSetting = BusinessSetting::where('tenant_id', tenant('id'))
                ->where('key', 'vat_enabled')
                ->first();
            $vatEnabled = $vatEnabledSetting ? filter_var($vatEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

            $vatAmount = $vatEnabled ? round($taxableAmount * 0.13, 2) : 0;
            $totalAmount = $taxableAmount + $vatAmount + $invoice->service_charge;
            $paidAmount = $data['paid_amount'] ?? 0;
            $dueAmount = $totalAmount - $paidAmount;

            $invoice->subtotal = $subtotal;
            $invoice->discount = $totalDiscount;
            $invoice->taxable_amount = $taxableAmount;
            $invoice->vat_amount = $vatAmount;
            $invoice->total = $totalAmount;
            $invoice->paid_amount = $paidAmount;
            $invoice->due_amount = $dueAmount;
            $invoice->save();

            foreach ($invoiceItems as $item) {
                $itemTaxable = $item->total_amount;
                $item->vat_amount = $vatEnabled ? round($itemTaxable * 0.13, 2) : 0;
                $item->save();
            }

            return $invoice->load('items');
        });
    }

    /**
     * Create an invoice from an Order.
     */
    public function createInvoiceFromOrder(Order $order, array $options = []): Invoice
    {
        return DB::transaction(function () use ($order, $options) {
            // Prevent duplicate active invoices from same order
            $allowDuplicate = $options['allow_duplicate'] ?? false;
            if (!$allowDuplicate) {
                $existingActiveInvoice = Invoice::where('order_id', $order->id)
                    ->whereIn('status', ['draft', 'issued', 'paid', 'partially_paid'])
                    ->first();
                if ($existingActiveInvoice) {
                    throw ValidationException::withMessages([
                        'order_id' => ['An active invoice already exists for this order.']
                    ]);
                }
            }

            $invoice = new Invoice();
            $invoice->order_id = $order->id;
            $invoice->customer_id = $order->customer_id;
            $invoice->invoice_number = $this->generateInvoiceNumber();
            $invoice->status = 'draft';
            $invoice->invoice_date = now()->toDateString();
            $invoice->due_date = now()->addDays(7)->toDateString(); // Default 7 days due terms
            $invoice->notes = $order->notes;
            $invoice->service_charge = $order->service_charge_amount ?? 0;

            // Subtotals
            $invoice->subtotal = $order->subtotal;
            $invoice->discount = $order->discount_amount;
            $invoice->taxable_amount = $order->subtotal - $order->discount_amount;
            $invoice->vat_amount = $order->vat_amount;
            $invoice->total = $order->total;
            
            // Map payment state if available in this phase
            $invoice->paid_amount = $order->paid_amount ?? 0;
            $invoice->due_amount = $invoice->total - $invoice->paid_amount;
            $invoice->save();

            // Resolve VAT dynamically
            $vatEnabledSetting = BusinessSetting::where('tenant_id', tenant('id'))
                ->where('key', 'vat_enabled')
                ->first();
            $vatEnabled = $vatEnabledSetting ? filter_var($vatEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

            // Snapshot order items
            foreach ($order->items as $orderItem) {
                $item = new InvoiceItem();
                $item->invoice_id = $invoice->id;
                $item->order_item_id = $orderItem->id;
                $item->menu_item_id = $orderItem->menu_item_id;
                $item->product_id = $orderItem->product_id;
                $item->service_id = $orderItem->service_id;
                $item->name = $orderItem->name;
                $item->quantity = $orderItem->quantity;
                $item->unit_price = $orderItem->unit_price;
                $item->discount_amount = $orderItem->discount_amount;
                $item->total_amount = $orderItem->total_amount;
                $item->vat_amount = $orderItem->vat_amount ?? ($vatEnabled ? round($orderItem->total_amount * 0.13, 2) : 0);
                $item->save();
            }

            return $invoice->load('items');
        });
    }

    /**
     * Update a draft invoice.
     */
    public function updateInvoice(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            // Locking check: Prevent editing issued/paid/cancelled invoice except notes
            if ($invoice->status !== 'draft') {
                $keys = array_keys($data);
                if (count($keys) === 1 && $keys[0] === 'notes') {
                    // Only notes updated, allowed!
                    $invoice->notes = $data['notes'];
                    $invoice->save();
                    return $invoice;
                }
                
                throw ValidationException::withMessages([
                    'status' => ['Only draft invoices can be modified.']
                ]);
            }

            $invoice->customer_id = $data['customer_id'] ?? $invoice->customer_id;
            $invoice->invoice_date = $data['invoice_date'] ?? $invoice->invoice_date;
            $invoice->due_date = $data['due_date'] ?? $invoice->due_date;
            $invoice->notes = $data['notes'] ?? $invoice->notes;
            $invoice->service_charge = $data['service_charge_amount'] ?? $invoice->service_charge;
            $invoice->save();

            // Recalculate items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                $invoice->items()->delete();

                $subtotal = 0;
                $totalItemDiscounts = 0;
                $invoiceItems = [];

                foreach ($data['items'] as $itemData) {
                    $item = new InvoiceItem();
                    $item->invoice_id = $invoice->id;
                    $item->menu_item_id = $itemData['menu_item_id'] ?? null;
                    $item->product_id = $itemData['product_id'] ?? null;
                    $item->service_id = $itemData['service_id'] ?? null;
                    $item->quantity = $itemData['quantity'];

                    $resolvedName = null;
                    $resolvedPrice = null;

                    if ($item->menu_item_id) {
                        $menuItem = MenuItem::findOrFail($item->menu_item_id);
                        $resolvedName = $menuItem->name;
                        $resolvedPrice = $menuItem->price;
                    } elseif ($item->product_id) {
                        $product = Product::findOrFail($item->product_id);
                        $resolvedName = $product->name;
                        $resolvedPrice = $product->price;
                    } elseif ($item->service_id) {
                        $svc = Service::findOrFail($item->service_id);
                        $resolvedName = $svc->name;
                        $resolvedPrice = $svc->price;
                    }

                    $item->name = $resolvedName ?? $itemData['name'] ?? 'Custom Item';
                    $unitPrice = $resolvedPrice ?? $itemData['unit_price'] ?? 0;
                    $item->unit_price = $unitPrice;

                    $itemSubtotal = $item->quantity * $unitPrice;
                    $itemDiscount = $itemData['discount_amount'] ?? 0;

                    if ($itemDiscount > $itemSubtotal) {
                        $itemDiscount = $itemSubtotal;
                    }

                    $item->discount_amount = $itemDiscount;
                    $item->total_amount = $itemSubtotal - $itemDiscount;

                    $subtotal += $itemSubtotal;
                    $totalItemDiscounts += $itemDiscount;

                    $invoiceItems[] = $item;
                }

                $invoiceLevelDiscount = $data['discount_amount'] ?? 0;
                $totalDiscount = $totalItemDiscounts + $invoiceLevelDiscount;

                if ($totalDiscount > $subtotal) {
                    $totalDiscount = $subtotal;
                }

                $taxableAmount = $subtotal - $totalDiscount;

                $vatEnabledSetting = BusinessSetting::where('tenant_id', tenant('id'))
                    ->where('key', 'vat_enabled')
                    ->first();
                $vatEnabled = $vatEnabledSetting ? filter_var($vatEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

                $vatAmount = $vatEnabled ? round($taxableAmount * 0.13, 2) : 0;
                $totalAmount = $taxableAmount + $vatAmount + $invoice->service_charge;
                $paidAmount = $data['paid_amount'] ?? $invoice->paid_amount;
                $dueAmount = $totalAmount - $paidAmount;

                $invoice->subtotal = $subtotal;
                $invoice->discount = $totalDiscount;
                $invoice->taxable_amount = $taxableAmount;
                $invoice->vat_amount = $vatAmount;
                $invoice->total = $totalAmount;
                $invoice->due_amount = $dueAmount;
                $invoice->save();

                foreach ($invoiceItems as $item) {
                    $itemTaxable = $item->total_amount;
                    $item->vat_amount = $vatEnabled ? round($itemTaxable * 0.13, 2) : 0;
                    $item->save();
                }
            } else {
                // Just update invoice-level values if discount changed
                $subtotal = $invoice->subtotal;
                $totalItemDiscounts = $invoice->items()->sum('discount_amount');
                $invoiceLevelDiscount = $data['discount_amount'] ?? $invoice->discount - $totalItemDiscounts;
                $totalDiscount = $totalItemDiscounts + $invoiceLevelDiscount;

                if ($totalDiscount > $subtotal) {
                    $totalDiscount = $subtotal;
                }

                $taxableAmount = $subtotal - $totalDiscount;

                $vatEnabledSetting = BusinessSetting::where('tenant_id', tenant('id'))
                    ->where('key', 'vat_enabled')
                    ->first();
                $vatEnabled = $vatEnabledSetting ? filter_var($vatEnabledSetting->value, FILTER_VALIDATE_BOOLEAN) : false;

                $vatAmount = $vatEnabled ? round($taxableAmount * 0.13, 2) : 0;
                $totalAmount = $taxableAmount + $vatAmount + $invoice->service_charge;
                $paidAmount = $data['paid_amount'] ?? $invoice->paid_amount;
                $dueAmount = $totalAmount - $paidAmount;

                $invoice->discount = $totalDiscount;
                $invoice->taxable_amount = $taxableAmount;
                $invoice->vat_amount = $vatAmount;
                $invoice->total = $totalAmount;
                $invoice->due_amount = $dueAmount;
                $invoice->save();
            }

            return $invoice->load('items');
        });
    }

    /**
     * Issue invoice.
     */
    public function issueInvoice(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->status = 'issued';
            $invoice->save();

            // Generate invoice PDF
            $pdfPath = $this->pdfService->generateInvoicePdf($invoice);
            $invoice->pdf_path = $pdfPath;
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Cancel invoice.
     */
    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->status = 'cancelled';
            $invoice->save();
            return $invoice;
        });
    }

    /**
     * Auto generate sequential invoice number per tenant.
     */
    protected function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $nextNumber = Invoice::where('invoice_number', 'like', "INV-{$year}-%")->count() + 1;
        do {
            $invoiceNumber = "INV-{$year}-" . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Invoice::where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }
}
