<?php
declare(strict_types=1);
namespace Pena\Service;
use Closure;

/** User-controlled AI destinations must stay on public HTTPS addresses, including after DNS resolution. */
final class AiEndpoint
{
    public static function normalize(string $url):string
    {
        if(strlen($url)>500||!preg_match('#^https://[a-zA-Z0-9.-]+(?::443)?(?:/[a-zA-Z0-9._/-]*)?$#D',$url))throw new PenaException('Gunakan base URL HTTPS tanpa kredensial, query, atau fragmen. Hanya port 443 dan path huruf, angka, garis miring, titik, garis bawah atau tanda hubung yang diterima.');
        $parts=parse_url($url);$host=strtolower($parts['host']??'');
        if(!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||!str_contains($host,'.')||filter_var($host,FILTER_VALIDATE_IP)||preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid|onion)$/D',$host)||str_ends_with($host,'.'))throw new PenaException('Gunakan nama domain publik untuk endpoint AI. Alamat IP dan domain lokal tidak diterima.');
        $path=$parts['path']??'';
        if(str_contains($path,'%')||str_contains($path,'..')||str_ends_with(rtrim($path,'/'),'/chat/completions'))throw new PenaException('Isi base URL, misalnya https://ai.contoh.com/v1, tanpa /chat/completions atau karakter terenkode.');
        return 'https://'.$host.rtrim($path,'/');
    }

    /** @return array<int,mixed> Validated addresses are pinned: cURL must not resolve the host again. */
    public static function options(string $url,?Closure $resolver=null):array
    {
        $base=str_ends_with($url,'/chat/completions')?substr($url,0,-17):$url;
        $base=self::normalize($base);$host=(string)parse_url($base,PHP_URL_HOST);
        $records=$resolver?$resolver($host):@dns_get_record($host,DNS_A|DNS_AAAA);
        $ips=[];
        foreach(is_array($records)?$records:[]as$record){
            $ip=$record['ip']??$record['ipv6']??null;
            if($ip===null)continue;
            if(!is_string($ip)||!self::isPublic($ip))throw new PenaException('Endpoint AI mengarah ke alamat yang tidak diizinkan. Gunakan layanan pada jaringan publik.');
            $ips[]=$ip;
        }
        if(!$ips)throw new PenaException('Domain endpoint AI belum memiliki alamat publik yang dapat digunakan.');
        $ip=$ips[0];
        return [CURLOPT_RESOLVE=>[$host.':443:'.(str_contains($ip,':')?'['.$ip.']':$ip)],
            CURLOPT_PROXY=>'',CURLOPT_NOPROXY=>'*',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];
    }

    private static function isPublic(string $ip):bool
    {
        if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
        $ranges=str_contains($ip,':')?['2001::/23','2001:db8::/32','2002::/16','3fff::/20']:
            ['0.0.0.0/8','10.0.0.0/8','100.64.0.0/10','127.0.0.0/8','169.254.0.0/16','172.16.0.0/12','192.0.0.0/24','192.0.2.0/24','192.88.99.0/24','192.168.0.0/16','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4','168.63.129.16/32'];
        if(str_contains($ip,':')&&!self::inRange($ip,'2000::/3'))return false;
        foreach($ranges as$range)if(self::inRange($ip,$range))return false;
        return true;
    }

    private static function inRange(string $ip,string $range):bool
    {
        [$network,$bits]=explode('/',$range);$address=inet_pton($ip);$prefix=inet_pton($network);
        if($address===false||$prefix===false||strlen($address)!==strlen($prefix))return false;
        $bytes=intdiv((int)$bits,8);$remainder=(int)$bits%8;
        return substr($address,0,$bytes)===substr($prefix,0,$bytes)&&(!$remainder||((ord($address[$bytes])^ord($prefix[$bytes]))&(255<<(8-$remainder)))===0);
    }
}
