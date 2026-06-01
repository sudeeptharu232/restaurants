<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business Cloud API Service.
 *
 * PRODUCTION REQUIREMENTS:
 * - WHATSAPP_API_URL        e.g. https://graph.facebook.com/v19.0
 * - WHATSAPP_PHONE_NUMBER_ID your WhatsApp Business Phone Number ID
 * - WHATSAPP_ACCESS_TOKEN   permanent system user token from Meta
 * - WHATSAPP_TEMPLATE_NAME  optional template name (for template messages)
 * - WHATSAPP_DEFAULT_COUNTRY_CODE default 977 (Nepal)
 *
 * If env vars are missing, the service operates in SANDBOX mode:
 * - Returns a simulated response marked as sandbox
 * - NEVER fakes a real success status
 */
class WhatsAppBusinessService
{
    protected string $apiUrl;
    protected string $phoneNumberId;
    protected string $accessToken;
    protected string $defaultCountryCode;
    protected bool $sandboxMode;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
        $this->accessToken = config('services.whatsapp.access_token', '');
        $this->defaultCountryCode = config('services.whatsapp.default_country_code', '977');

        // Sandbox mode if any required credential is missing
        $this->sandboxMode = empty($this->apiUrl)
            || empty($this->phoneNumberId)
            || empty($this->accessToken);
    }

    /**
     * Format a phone number to E.164 format.
     * Strips all non-digits, prepends country code if not present.
     */
    public function formatPhone(string $phone): string
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // If Nepal number (10 digits starting with 98), prepend country code
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return $this->defaultCountryCode . $digits;
        }

        // If already has country code (12+ digits for Nepal)
        if (strlen($digits) >= 12) {
            return $digits;
        }

        // Default: prepend default country code
        return $this->defaultCountryCode . $digits;
    }

    /**
     * Build the WhatsApp Cloud API text message payload.
     */
    public function buildTextPayload(string $to, string $message): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->formatPhone($to),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];
    }

    /**
     * Send a text message via WhatsApp Business Cloud API.
     *
     * Returns normalized result:
     * [
     *   'success' => bool,
     *   'message_id' => string|null,
     *   'error' => string|null,
     *   'sandbox' => bool,
     * ]
     */
    public function sendTextMessage(string $to, string $message): array
    {
        if ($this->sandboxMode) {
            Log::info('[WhatsApp] SANDBOX MODE: message not sent', [
                'to' => $to,
                'message_preview' => substr($message, 0, 100),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => 'WhatsApp credentials not configured. Running in sandbox mode.',
                'sandbox' => true,
            ];
        }

        $url = rtrim($this->apiUrl, '/') . '/' . $this->phoneNumberId . '/messages';
        $payload = $this->buildTextPayload($to, $message);

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? null;

                Log::info('[WhatsApp] Message sent successfully', [
                    'to' => $to,
                    'message_id' => $messageId,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'error' => null,
                    'sandbox' => false,
                ];
            }

            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? ('HTTP ' . $response->status());

            Log::warning('[WhatsApp] API returned error', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMessage,
                'sandbox' => false,
            ];
        } catch (\Exception $e) {
            Log::error('[WhatsApp] Exception sending message', [
                'to' => $to,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'sandbox' => false,
            ];
        }
    }

    /**
     * Check if running in sandbox mode.
     */
    public function isSandbox(): bool
    {
        return $this->sandboxMode;
    }
}
