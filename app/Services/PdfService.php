<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfService
{
    /**
     * Generate invoice PDF and save to storage.
     */
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $tenantId = tenant('id');
        $pdfPath = "invoices/{$tenantId}/{$invoice->invoice_number}.pdf";

        // Fetch settings dynamically from the central settings database
        $businessName = BusinessSetting::where('tenant_id', $tenantId)->where('key', 'business_name')->value('value') ?? 'Growstro Restaurant';
        $businessAddress = BusinessSetting::where('tenant_id', $tenantId)->where('key', 'business_address')->value('value') ?? 'Kathmandu, Nepal';
        $panNumber = BusinessSetting::where('tenant_id', $tenantId)->where('key', 'pan_number')->value('value') ?? 'N/A';
        $vatEnabled = filter_var(BusinessSetting::where('tenant_id', $tenantId)->where('key', 'vat_enabled')->value('value') ?? 'false', FILTER_VALIDATE_BOOLEAN);

        $data = [
            'invoice' => $invoice->load(['items', 'customer', 'order']),
            'businessName' => $businessName,
            'businessAddress' => $businessAddress,
            'panNumber' => $panNumber,
            'vatRegistered' => $vatEnabled ? 'Yes' : 'No',
        ];

        // Compile template
        $pdf = Pdf::loadView('pdf.invoice', $data);

        // Ensure folder structure
        Storage::disk('local')->makeDirectory("invoices/{$tenantId}");

        // Store
        Storage::disk('local')->put($pdfPath, $pdf->output());

        return $pdfPath;
    }

    /**
     * Stream download response for invoice.
     */
    public function downloadInvoicePdf(Invoice $invoice)
    {
        $tenantId = tenant('id');
        $expectedSub = "invoices/{$tenantId}/";

        // Tenant Isolation Check
        if (!$invoice->pdf_path || !str_starts_with($invoice->pdf_path, $expectedSub)) {
            abort(403, "Access Denied: You do not have access to this document.");
        }

        $fullPath = Storage::disk('local')->path($invoice->pdf_path);

        if (!file_exists($fullPath)) {
            // Generate on demand if missing
            $this->generateInvoicePdf($invoice);
        }

        return response()->download($fullPath, "{$invoice->invoice_number}.pdf", [
            'Content-Type' => 'application/pdf'
        ]);
    }
}
