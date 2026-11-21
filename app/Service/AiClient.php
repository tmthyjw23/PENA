<?php
declare(strict_types=1);

namespace Pena\Service;

use Closure;

/**
 * Minimal client for an OpenAI-compatible chat endpoint (9router: http://127.0.0.1:20128/v1).
 * Platform configuration comes from the environment; AiSettings supplies owner-scoped credentials.
 * The transport is injectable so tests never touch the network.
 */
final class AiClient
{
    /** @var Closure(string $url, array $headers, string $body, int $timeout): array{0:int,1:string,2?:array<string,string>} */
    private readonly Closure $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly int $timeout = 45,
        ?Closure $transport = null,
        private readonly bool $openAiParameters = false,
        bool $publicEndpoint = false,
    ) {
        $this->transport = $transport ?? ($publicEndpoint
            ? static fn(string $url,array $headers,string $body,int $timeout)=>self::curlTransport($url,$headers,$body,$timeout,true)
            : self::curlTransport(...));
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
    public function chat(array $messages, float $temperature = 0.2, ?int $maxOutputTokens = null): array
    {
        $payload=['model'=>$this->model,'messages'=>$messages,'stream'=>false];
        $limit=max(128,min(4096,(int)($maxOutputTokens??$_ENV['AI_MAX_TOKENS']??1500)));
        // OpenAI reasoning models reject legacy max_tokens and custom temperature.
        if($this->openAiParameters)$payload['max_completion_tokens']=$limit;
        else{$payload['max_tokens']=$limit;$payload['temperature']=$temperature;}
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->apiKey !== null) $headers[] = 'Authorization: Bearer ' . $this->apiKey;

        $started = hrtime(true);
        $response = ($this->transport)($this->baseUrl . '/chat/completions', $headers, $body, $this->timeout);
        [$status,$raw]=$response;
        $latency = (int)((hrtime(true) - $started) / 1_000_000);

        $data = json_decode($raw, true);
        if ($status === 429) {
            $retry=$response[2]['retry-after']??'';
            $seconds=is_string($retry)&&ctype_digit($retry)?min(604800,(int)$retry):0;
            if(is_string($retry)&&$seconds===0&&($date=strtotime($retry))!==false)$seconds=max(0,min(604800,$date-time()));
            $wait='';
            if($seconds>0){
                $minutes=(int)ceil($seconds/60);
                $duration=$seconds<60?$seconds.' detik':($minutes<60?$minutes.' menit':intdiv($minutes,60).' jam'.($minutes%60?' '.($minutes%60).' menit':''));
                $wait=' Perkiraan waktu tunggu dari layanan: '.$duration.'.';
            }
            throw new AiException('Batas pemakaian layanan AI tercapai (HTTP 429).'.$wait.' Coba lagi nanti atau lanjutkan secara manual.',$latency);
        }
        if ($status < 200 || $status >= 300) {
            // Provider error bodies can contain credentials or upstream internals.
            throw new AiException('Layanan AI belum dapat memproses permintaan (HTTP '.$status.'). Coba lagi nanti atau lanjutkan secara manual.', $latency);
        }
        $text = is_array($data) ? ($data['choices'][0]['message']['content'] ?? null) : null;
        if (!is_string($text) || trim($text) === '') throw new AiException('Layanan AI mengirim jawaban kosong.', $latency);
        // A custom gateway must not reflect the bearer credential into drafts or logs.
        if($this->apiKey!==null&&$this->apiKey!==''){
            $text=str_replace($this->apiKey,'[kunci disamarkan]',$text);
            if(is_string($data['model']??null))$data['model']=str_replace($this->apiKey,'[kunci disamarkan]',$data['model']);
        }
        return [
            'text' => $text, 'finish_reason'=>in_array($data['choices'][0]['finish_reason']??null,['stop','length','content_filter','tool_calls','function_call'],true)?$data['choices'][0]['finish_reason']:null,
            'model' => (string)($data['model'] ?? $this->model),
            'input_tokens' => isset($data['usage']['prompt_tokens']) ? (int)$data['usage']['prompt_tokens'] : null,
            'output_tokens' => isset($data['usage']['completion_tokens']) ? (int)$data['usage']['completion_tokens'] : null,
            'latency_ms' => $latency,
        ];
    }

    /** @return array{0:int,1:string,2:array<string,string>} */
    private static function curlTransport(string $url, array $headers, string $body, int $timeout, bool $publicEndpoint=false): array
    {
        $options=[];
        if($publicEndpoint){
            try{$options=AiEndpoint::options($url);}catch(PenaException $e){throw new AiException($e->getMessage());}
        }
        $ch = curl_init($url);
        $responseHeaders=[];$responseBody='';$tooLarge=false;
        curl_setopt_array($ch, [
            CURLOPT_WRITEFUNCTION => static function($handle,string $chunk)use(&$responseBody,&$tooLarge):int {
                if(strlen($responseBody)+strlen($chunk)>1048576){$tooLarge=true;return 0;}
                $responseBody.=$chunk;return strlen($chunk);
            },
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function($handle,string $line)use(&$responseHeaders):int {
                if(str_starts_with(strtolower($line),'retry-after:'))$responseHeaders['retry-after']=trim(substr($line,12));
                return strlen($line);
            },
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $timeout,
        ]);
        if($options)curl_setopt_array($ch,$options);
        $raw = curl_exec($ch);
        if($tooLarge)throw new AiException('Jawaban provider terlalu besar. Gunakan model teks tanpa lampiran.');
        if ($raw === false) {
            $timedOut = curl_errno($ch) === CURLE_OPERATION_TIMEDOUT;
            $message = $timedOut ? "Layanan AI tidak merespons dalam $timeout detik." : 'Layanan AI tidak bisa dihubungi. Periksa koneksi provider atau coba lagi nanti.';
            throw new AiException($message);
        }
        return [(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string)$responseBody, $responseHeaders];
    }
}
