<?php
declare(strict_types=1);

namespace Pena\Service;

use Closure;

/**
 * Minimal client for an OpenAI-compatible chat endpoint (9router: http://127.0.0.1:20128/v1).
 * Configuration comes from the environment only: AI_BASE_URL, AI_API_KEY, AI_MODEL, AI_TIMEOUT.
 * The transport is injectable so tests never touch the network.
 */
final class AiClient
{
    /** @var Closure(string $url, array $headers, string $body, int $timeout): array{0:int,1:string} */
    private readonly Closure $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 45,
        ?Closure $transport = null,
    ) {
        $this->transport = $transport ?? self::curlTransport(...);
    }

    /** Null when AI is not configured, so pages can say so instead of failing later. */
    public static function fromEnv(?Closure $transport = null): ?self
    {
        $base = trim((string)($_ENV['AI_BASE_URL'] ?? ''));
        $model = trim((string)($_ENV['AI_MODEL'] ?? ''));
        if ($base === '' || $model === '') return null;
        $key = trim((string)($_ENV['AI_API_KEY'] ?? ''));
        $timeout = max(5, min(120, (int)($_ENV['AI_TIMEOUT'] ?? 45)));
        return new self(rtrim($base, '/'), $model, $key === '' ? null : $key, $timeout, $transport);
    }

    public function model(): string { return $this->model; }

    public function timeout(): int { return $this->timeout; }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     * @return array{text:string, model:string, input_tokens:?int, output_tokens:?int, latency_ms:int}
     * @throws AiException when the service is unreachable, times out, or answers with an error
     */
    public function chat(array $messages, float $temperature = 0.2): array
    {
        $body = json_encode(['model' => $this->model, 'messages' => $messages, 'temperature' => $temperature, 'stream' => false],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->apiKey !== null) $headers[] = 'Authorization: Bearer ' . $this->apiKey;

        $started = hrtime(true);
        [$status, $raw] = ($this->transport)($this->baseUrl . '/chat/completions', $headers, $body, $this->timeout);
        $latency = (int)((hrtime(true) - $started) / 1_000_000);

        $data = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $detail = is_array($data) ? (string)($data['error']['message'] ?? $data['error'] ?? '') : '';
            throw new AiException('Layanan AI menolak permintaan (HTTP ' . $status . ($detail !== '' ? ': ' . mb_substr($detail, 0, 160) : '') . ').', $latency);
        }
        $text = is_array($data) ? ($data['choices'][0]['message']['content'] ?? null) : null;
        if (!is_string($text) || trim($text) === '') throw new AiException('Layanan AI mengirim jawaban kosong.', $latency);
        return [
            'text' => $text,
            'model' => (string)($data['model'] ?? $this->model),
            'input_tokens' => isset($data['usage']['prompt_tokens']) ? (int)$data['usage']['prompt_tokens'] : null,
            'output_tokens' => isset($data['usage']['completion_tokens']) ? (int)$data['usage']['completion_tokens'] : null,
            'latency_ms' => $latency,
        ];
    }

    /** @return array{0:int,1:string} */
    private static function curlTransport(string $url, array $headers, string $body, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $timedOut = curl_errno($ch) === CURLE_OPERATION_TIMEDOUT;
            $message = $timedOut ? "Layanan AI tidak merespons dalam $timeout detik." : 'Layanan AI tidak bisa dihubungi. Pastikan 9router berjalan.';
            throw new AiException($message);
        }
        return [(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string)$raw];
    }
}
