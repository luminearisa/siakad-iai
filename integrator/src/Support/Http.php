<?php

declare(strict_types=1);

namespace Integrator\Support;

use RuntimeException;

/**
 * Small HTTP client built on cURL: JSON in, JSON out, with retries, timeouts and an
 * explicit switch for self-signed certificates (many Neo Feeder installs sit behind
 * an internal TLS certificate that the OS store does not know).
 */
final class Http
{
    public function __construct(
        private int $timeout = 30,
        private int $retries = 2,
        private bool $verifySsl = true
    ) {
    }

    public function withVerifySsl(bool $verify): self
    {
        $clone = clone $this;
        $clone->verifySsl = $verify;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $this->request('GET', $url, $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function postJson(string $url, array $payload, array $headers = []): HttpResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';

        return $this->request('POST', $url, $headers, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('Ekstensi cURL PHP wajib aktif.');
        }

        $attempt = 0;
        $lastError = null;

        while ($attempt <= $this->retries) {
            $attempt++;

            $handle = curl_init();

            $headerLines = [];

            foreach ($headers as $name => $value) {
                $headerLines[] = $name.': '.$value;
            }

            curl_setopt_array($handle, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
                CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            ]);

            if ($body !== null) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            }

            $raw = curl_exec($handle);
            $error = curl_error($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            curl_close($handle);

            if ($raw === false) {
                $lastError = $error !== '' ? $error : 'Koneksi gagal.';

                if ($attempt <= $this->retries) {
                    sleep($attempt);
                    continue;
                }

                return new HttpResponse(0, '', [], $lastError);
            }

            $rawHeaders = substr((string) $raw, 0, $headerSize);
            $bodyContent = substr((string) $raw, $headerSize);

            $response = new HttpResponse($status, $bodyContent, $this->parseHeaders($rawHeaders), null);

            // Retry transport-level failures and upstream hiccups only; 4xx is final.
            if (in_array($status, [429, 500, 502, 503, 504], true) && $attempt <= $this->retries) {
                sleep($attempt);
                continue;
            }

            return $response;
        }

        return new HttpResponse(0, '', [], $lastError ?? 'Permintaan gagal.');
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $raw): array
    {
        $headers = [];

        foreach (explode("\r\n", trim($raw)) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[trim(strtolower($name))] = trim($value);
        }

        return $headers;
    }
}
