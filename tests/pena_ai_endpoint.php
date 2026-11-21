<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/init.php';
use Pena\Service\{AiEndpoint,PenaException};
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function denies(callable $fn):void{try{$fn();}catch(PenaException){return;}throw new RuntimeException('Expected endpoint rejection');}
foreach(['http://public.example/v1','https://localhost/v1','https://127.0.0.1/v1','https://[::1]/v1','https://2130706433/v1','https://user:pass@public.example/v1','https://public.example:8080/v1','https://public.example/v1?key=secret','https://public.example/v1#secret','https://public.example./v1','https://public.example/v1/chat/completions','https://public.example/%2e%2e/internal','https://public.example/a/../internal','https://public.example\\@127.0.0.1/v1']as$url)denies(fn()=>AiEndpoint::normalize($url));
check(AiEndpoint::normalize('https://PUBLIC.example:443/api/v1/')==='https://public.example/api/v1','Endpoint normalization incorrect');
$url='https://public.example/v1/chat/completions';
foreach(['127.0.0.1','10.0.0.1','100.64.0.1','169.254.169.254','172.16.1.1','192.168.1.1','192.0.0.8','168.63.129.16','198.18.0.1','224.0.0.1','0.0.0.0','::1','fc00::1','fe80::1','::ffff:127.0.0.1','64:ff9b::a00:1','2001::1','2002:7f00:1::1','2001:db8::1','3fff::1']as$ip){$resolver=static fn()=>[['ip'=>$ip]];denies(fn()=>AiEndpoint::options($url,$resolver));}
denies(fn()=>AiEndpoint::options($url,static fn()=>[['ip'=>'1.1.1.1'],['ip'=>'10.0.0.1']]));
denies(fn()=>AiEndpoint::options($url,static fn()=>[]));
$options=AiEndpoint::options($url,static fn()=>[['ip'=>'1.1.1.1']]);
check($options[CURLOPT_RESOLVE]===['public.example:443:1.1.1.1'],'DNS address not pinned');
check($options[CURLOPT_PROXY]===''&&$options[CURLOPT_FOLLOWLOCATION]===false&&$options[CURLOPT_PROTOCOLS]===CURLPROTO_HTTPS&&$options[CURLOPT_SSL_VERIFYPEER]&&$options[CURLOPT_SSL_VERIFYHOST]===2,'Outbound transport restrictions missing');
$options=AiEndpoint::options($url,static fn()=>[['ipv6'=>'2606:4700:4700::1111']]);
check($options[CURLOPT_RESOLVE]===['public.example:443:[2606:4700:4700::1111]'],'Public IPv6 pin incorrect');
echo "PASS endpoint policy: HTTPS/443, normalized domains, rejected credentials/query/fragment/IP literals, private/reserved IPv4/IPv6, mixed DNS, pinning, no proxy/redirect and TLS verification\n";
