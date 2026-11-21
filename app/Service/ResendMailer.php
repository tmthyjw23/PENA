<?php
declare(strict_types=1);
namespace Pena\Service;

use Closure;

final class ResendMailer
{
    private readonly Closure $transport;

    public function __construct(private readonly string $key, private readonly string $from, ?Closure $transport = null)
    {
        $this->transport = $transport ?? self::curlTransport(...);
    }

    public static function fromEnv(?Closure $transport = null): self
    {
        $key = trim((string)($_ENV['RESEND_API_KEY'] ?? ''));
        $from = trim((string)($_ENV['MAIL_FROM'] ?? 'PENA <noreply@notify.egg3l.com>'));
        if ($key === '' || preg_match('/[\r\n]/', $key.$from)) {
            throw new PenaException('Layanan email belum tersedia. Silakan hubungi pengelola.');
        }
        return new self($key, $from, $transport);
    }

    /** API acceptance is not proof of inbox delivery. Never log request/response bodies. */
    public function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        $message = ['from'=>$this->from, 'to'=>[$to], 'subject'=>$subject, 'text'=>$text];
        if ($html !== null) $message['html'] = $html;
        $body = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        try {
            [$status, $raw] = ($this->transport)('https://api.resend.com/emails',
                ['Authorization: Bearer '.$this->key, 'Content-Type: application/json', 'Accept: application/json'], $body);
            $response = json_decode($raw, true);
            if ($status < 200 || $status >= 300 || !is_string($response['id'] ?? null) || $response['id'] === '') {
                error_log('PENA email: provider rejected request, HTTP '.(int)$status);
                throw new PenaException('Email belum berhasil diproses. Silakan coba lagi nanti.');
            }
        } catch (PenaException $e) {
            throw $e;
        } catch (\Throwable) {
            // Transport exceptions can include credentials: do not forward their messages.
            error_log('PENA email: transport failure');
            throw new PenaException('Email belum berhasil diproses. Silakan coba lagi nanti.');
        }
    }

    private static function curlTransport(string $url, array $headers, string $body): array
    {
        $curl = curl_init($url);
        try {
            curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_POSTFIELDS=>$body, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5,
                CURLOPT_TIMEOUT=>15, CURLOPT_FOLLOWLOCATION=>false]);
            $raw = curl_exec($curl);
            if ($raw === false) throw new PenaException('Layanan email tidak dapat dihubungi. Coba lagi nanti.');
            return [(int)curl_getinfo($curl, CURLINFO_HTTP_CODE), $raw];
        } finally {
            curl_close($curl);
        }
    }
}
