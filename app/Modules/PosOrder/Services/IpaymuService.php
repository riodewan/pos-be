<?php

namespace App\Modules\PosOrder\Services;

use App\Core\Enums\PaymentMethod;
use App\Core\Enums\PaymentStatus;
use App\Modules\PosOrder\Models\Order;
use App\Modules\PosOrder\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class IpaymuService
{
    protected string $va;
    protected string $apiKey;
    protected string $baseUrl;
    protected string $env;
    protected string $notifyUrl;

    public function __construct()
    {
        $this->va = (string) config('ipaymu.va', env('IPAYMU_VA', '0000007882798449'));
        $this->apiKey = (string) config('ipaymu.api_key', env('IPAYMU_API_KEY', 'SANDBOX7B6D8133-D6FF-4443-93E0-95E60DA70F29'));
        $this->baseUrl = rtrim((string) config('ipaymu.base_url', env('IPAYMU_URL', 'https://sandbox.ipaymu.com/api/v2')), '/');
        $this->env = (string) config('ipaymu.env', env('IPAYMU_ENV', 'sandbox'));
        $this->notifyUrl = (string) config('ipaymu.notify_url', env('IPAYMU_NOTIFY_URL', 'https://sandbox.ipaymu.com/notify'));
    }

    /**
     * Send authenticated request to iPaymu API v2.
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $method = strtoupper($method);
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $body = json_encode($data, JSON_UNESCAPED_SLASHES);
        $requestBodyHex = strtolower(hash('sha256', $body));
        $stringToSign = $method . ':' . $this->va . ':' . $requestBodyHex . ':' . $this->apiKey;
        $signature = hash_hmac('sha256', $stringToSign, $this->apiKey);
        $timestamp = date('YmdHis');

        $headers = [
            'Content-Type: application/json',
            'va: ' . $this->va,
            'signature: ' . $signature,
            'timestamp: ' . $timestamp,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 25,
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('iPaymu cURL error', ['endpoint' => $endpoint, 'error' => $curlError]);
            throw new RuntimeException("Gagal menghubungi gateway iPaymu: {$curlError}");
        }

        $decoded = json_decode($rawResponse, true);

        if ($httpCode >= 400 || empty($decoded['Success'])) {
            $msg = $decoded['Message'] ?? "iPaymu request failed with HTTP {$httpCode}";
            Log::warning('iPaymu API error', ['endpoint' => $endpoint, 'code' => $httpCode, 'response' => $decoded]);
            throw new RuntimeException("iPaymu: {$msg}");
        }

        return $decoded['Data'] ?? [];
    }

    /**
     * Create dynamic QRIS payment via iPaymu.
     */
    public function createQris(Order $order, array $customer = []): array
    {
        $orderTotal = (int) round((float) $order->total_amount);
        if ($orderTotal <= 0) {
            throw new InvalidArgumentException('Total pesanan harus lebih besar dari 0.');
        }

        // Webhook URL: iPaymu strictly requires a public http(s) URL.
        // If app runs on localhost / 127.0.0.1, fallback to a valid public URL so iPaymu accepts the request.
        $notifyUrl = $this->notifyUrl;
        if (empty($notifyUrl)) {
            $appUrl = url('/api/public/payments/ipaymu/webhook');
            $host = parse_url($appUrl, PHP_URL_HOST);
            if (in_array($host, ['localhost', '127.0.0.1', '::1']) || str_ends_with((string) $host, '.local') || str_ends_with((string) $host, '.test')) {
                $notifyUrl = 'https://sandbox.ipaymu.com/notify';
            } else {
                $notifyUrl = $appUrl;
            }
        }

        $name = !empty($customer['name']) ? $customer['name'] : ($order->customer_name ?: 'Pelanggan');
        $phone = !empty($customer['phone']) ? $customer['phone'] : ($order->customer_phone ?: '081234567890');
        $email = !empty($customer['email']) ? $customer['email'] : 'customer@pos.local';

        $payload = [
            'name' => substr($name, 0, 50),
            'phone' => substr($phone, 0, 20),
            'email' => $email,
            'amount' => $orderTotal,
            'notifyUrl' => $notifyUrl,
            'paymentMethod' => 'qris',
            'paymentChannel' => 'mpm',
            'referenceId' => $order->order_number,
        ];

        $res = $this->request('POST', '/payment/direct', $payload);

        $rawQrImage = $res['QrImage'] ?? '';
        $resolvedQrImage = $rawQrImage;

        // If iPaymu returns an HTML /qris-basic/... URL, resolve the direct base64 image data URI
        if (!empty($rawQrImage) && filter_var($rawQrImage, FILTER_VALIDATE_URL)) {
            try {
                $ch = curl_init($rawQrImage);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ]);
                $html = curl_exec($ch);
                curl_close($ch);

                if (!empty($html) && preg_match('/src=["\'](data:image\/[^"\']+)["\']/', $html, $matches)) {
                    $resolvedQrImage = $matches[1];
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to resolve iPaymu QrImage base64', ['error' => $e->getMessage()]);
            }
        }

        $result = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'transaction_id' => $res['TransactionId'] ?? null,
            'session_id' => $res['SessionId'] ?? null,
            'reference_id' => $res['ReferenceId'] ?? $order->order_number,
            'amount' => $res['Total'] ?? $orderTotal,
            'qr_string' => $res['QrString'] ?? '',
            'qr_image' => $resolvedQrImage,
            'qr_url' => $rawQrImage,
            'qr_template' => $res['QrTemplate'] ?? '',
            'expired' => $res['Expired'] ?? '',
            'nmid' => $res['NMID'] ?? '',
        ];

        // Cache the active pending payment for quick status check
        Cache::put("ipaymu_order_{$order->id}", $result, now()->addHours(24));
        if (!empty($result['transaction_id'])) {
            Cache::put("ipaymu_trx_{$result['transaction_id']}", $order->id, now()->addHours(24));
        }

        return $result;
    }

    /**
     * Check transaction status with iPaymu API v2.
     */
    public function checkTransaction(int|string $transactionId): array
    {
        $res = $this->request('POST', '/transaction', [
            'transactionId' => (int) $transactionId,
        ]);

        $status = $res['Status'] ?? 0;
        $paidStatus = strtolower((string) ($res['PaidStatus'] ?? ''));
        $isPaid = ((int) $status === 1) && $paidStatus === 'paid';

        return [
            'is_paid' => $isPaid,
            'transaction_id' => $res['TransactionId'] ?? $transactionId,
            'session_id' => $res['SessionId'] ?? null,
            'reference_id' => $res['ReferenceId'] ?? null,
            'status' => $status,
            'status_desc' => $res['StatusDesc'] ?? '',
            'paid_status' => $res['PaidStatus'] ?? '',
            'amount' => (float) ($res['Amount'] ?? $res['SubTotal'] ?? 0),
            'fee' => (float) ($res['Fee'] ?? 0),
            'paid_date' => $res['SuccessDate'] ?? null,
            'payment_method' => $res['PaymentMethod'] ?? 'qris',
            'payment_channel' => $res['PaymentChannel'] ?? 'QRIS',
            'buyer_name' => $res['BuyerName'] ?? null,
            'buyer_phone' => $res['BuyerPhone'] ?? null,
            'raw' => $res,
        ];
    }
}
