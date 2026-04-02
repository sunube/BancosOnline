<?php

namespace FacturaScripts\Plugins\BancosOnline\Lib;

use FacturaScripts\Core\Tools;

/**
 * Cliente PHP para Enable Banking API (PSD2).
 * Genera JWT con RS256 via openssl y realiza peticiones REST.
 */
class EnableBankingAPI
{
    private const API_BASE = 'https://api.enablebanking.com';

    /** @var string */
    private $appId;

    /** @var string */
    private $keyPath;

    /** @var string */
    private $redirectUrl;

    /** @var string|null */
    private $token;

    /** @var int */
    private $tokenExp = 0;

    public function __construct(string $appId, string $keyPath, string $redirectUrl)
    {
        $this->appId = $appId;
        $this->keyPath = $keyPath;
        $this->redirectUrl = $redirectUrl;
    }

    // ─── JWT ─────────────────────────────────────────────────────

    /**
     * Genera un JWT firmado con RS256 para la API de Enable Banking.
     */
    private function getJwt(): string
    {
        $now = time();

        // reutilizar token si no ha expirado
        if ($this->token && $now < $this->tokenExp - 60) {
            return $this->token;
        }

        $privateKey = file_get_contents($this->keyPath);
        if (false === $privateKey) {
            throw new \RuntimeException('No se pudo leer la clave privada: ' . $this->keyPath);
        }

        // Header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->appId,
        ];

        // Payload
        $payload = [
            'iss' => 'enablebanking.com',
            'aud' => 'api.enablebanking.com',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header));
        $segments[] = self::base64UrlEncode(json_encode($payload));

        $signingInput = implode('.', $segments);

        $key = openssl_pkey_get_private($privateKey);
        if (false === $key) {
            throw new \RuntimeException('Clave privada invalida: ' . openssl_error_string());
        }

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Error al firmar JWT: ' . openssl_error_string());
        }

        $segments[] = self::base64UrlEncode($signature);

        $this->token = implode('.', $segments);
        $this->tokenExp = $payload['exp'];

        return $this->token;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ─── HTTP ────────────────────────────────────────────────────

    private function headers(): array
    {
        return [
            'Authorization: Bearer ' . $this->getJwt(),
            'Content-Type: application/json',
        ];
    }

    /**
     * GET request a la API.
     */
    private function get(string $path, array $params = []): array
    {
        $url = self::API_BASE . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Error cURL: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException('API ' . $httpCode . ': ' . substr($response, 0, 500));
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * POST request a la API.
     */
    private function post(string $path, array $data): array
    {
        $url = self::API_BASE . $path;
        $jsonData = json_encode($data);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Error cURL: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException('API ' . $httpCode . ': ' . substr($response, 0, 500));
        }

        return json_decode($response, true) ?? [];
    }

    // ─── Auth Flow ───────────────────────────────────────────────

    /**
     * Inicia el flujo de autorizacion PSD2 con un banco.
     *
     * @return array Con claves 'url' y 'authorization_id'
     */
    public function startAuth(string $bankName, string $country = 'ES', string $psuType = 'business', string $state = ''): array
    {
        $validUntil = (new \DateTime('+180 days', new \DateTimeZone('UTC')))->format('c');

        return $this->post('/auth', [
            'access' => [
                'balances' => true,
                'transactions' => true,
                'valid_until' => $validUntil,
            ],
            'aspsp' => [
                'name' => $bankName,
                'country' => $country,
            ],
            'psu_type' => $psuType,
            'redirect_url' => $this->redirectUrl,
            'state' => $state,
        ]);
    }

    /**
     * Crea una sesion tras el callback del banco.
     *
     * @return array Con 'session_id' y 'accounts'
     */
    public function createSession(string $code): array
    {
        return $this->post('/sessions', ['code' => $code]);
    }

    // ─── Data ────────────────────────────────────────────────────

    /**
     * Obtiene la lista de ASPSPs (bancos) disponibles en un pais.
     */
    public function getAspsps(string $country = 'ES'): array
    {
        $result = $this->get('/aspsps', ['country' => $country]);
        return $result['aspsps'] ?? [];
    }

    /**
     * Obtiene los saldos de una cuenta.
     */
    public function getBalances(string $accountUid): array
    {
        return $this->get('/accounts/' . urlencode($accountUid) . '/balances');
    }

    /**
     * Obtiene transacciones de una cuenta (una pagina).
     */
    public function getTransactions(string $accountUid, ?string $dateFrom = null, ?string $dateTo = null, ?string $continuationKey = null): array
    {
        $params = [];
        if ($dateFrom) {
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo) {
            $params['date_to'] = $dateTo;
        }
        if ($continuationKey) {
            $params['continuation_key'] = $continuationKey;
        }

        return $this->get('/accounts/' . urlencode($accountUid) . '/transactions', $params);
    }

    /**
     * Obtiene TODAS las transacciones con paginacion automatica.
     */
    public function getAllTransactions(string $accountUid, ?string $dateFrom = null, ?string $dateTo = null, int $maxPages = 20): array
    {
        $allTxs = [];
        $continuationKey = null;
        $page = 0;

        while ($page < $maxPages) {
            $result = $this->getTransactions($accountUid, $dateFrom, $dateTo, $continuationKey);
            $txs = $result['transactions'] ?? [];
            $allTxs = array_merge($allTxs, $txs);
            $page++;

            $continuationKey = $result['continuation_key'] ?? null;
            if (empty($continuationKey) || empty($txs)) {
                break;
            }
        }

        return $allTxs;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Parsea una transaccion de la API al formato de nuestro modelo.
     */
    public static function parseTransaction(array $tx): array
    {
        $amountObj = $tx['transaction_amount'] ?? [];
        $amountStr = $amountObj['amount'] ?? '0';
        $amount = (float) $amountStr;

        if (($tx['credit_debit_indicator'] ?? '') === 'DBIT') {
            $amount = -$amount;
        }

        // remittance_information puede ser null, lista o string
        $remittance = $tx['remittance_information'] ?? null;
        if (is_array($remittance)) {
            $description = implode('; ', array_filter($remittance));
        } elseif (is_string($remittance)) {
            $description = $remittance;
        } else {
            $description = '';
        }

        // creditor/debtor pueden ser null
        $creditor = $tx['creditor'] ?? [];
        $debtor = $tx['debtor'] ?? [];
        $counterparty = ($creditor['name'] ?? '') ?: ($debtor['name'] ?? '');

        $txId = $tx['entry_reference'] ?? ($tx['transaction_id'] ?? '');
        if (empty($txId)) {
            $txId = uniqid('tx_', true);
        }

        return [
            'tx_id' => $txId,
            'fecha' => $tx['booking_date'] ?? ($tx['transaction_date'] ?? ''),
            'importe' => $amount,
            'moneda' => $amountObj['currency'] ?? 'EUR',
            'descripcion' => $description,
            'contraparte' => $counterparty,
            'estado' => $tx['status'] ?? 'BOOK',
        ];
    }

    /**
     * Verifica la conexion con Enable Banking.
     */
    public function testConnection(): array
    {
        try {
            $result = $this->get('/application');
            return ['ok' => true, 'data' => $result];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
