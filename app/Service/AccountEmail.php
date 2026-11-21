<?php
declare(strict_types=1);
namespace Pena\Service;

/** Transactional email: table layout and inline styles for email clients, no remote assets. */
final class AccountEmail
{
    /** @return array{subject:string,text:string,html:string} */
    public static function compose(bool $verification, string $url): array
    {
        $title = $verification ? 'Konfirmasikan email Anda' : 'Atur ulang password Anda';
        $intro = $verification
            ? 'Konfirmasikan alamat email ini untuk mengakses akun PENA dan mulai mengelola surat pribadi maupun organisasi.'
            : 'Kami menerima permintaan untuk mengatur ulang password akun PENA Anda. Gunakan tombol berikut untuk membuat password baru.';
        $action = $verification ? 'Verifikasi email' : 'Buat password baru';
        $validity = 'Tautan ini berlaku '.($verification ? '24 jam' : '30 menit').' dan hanya dapat digunakan satu kali.';
        $safety = $verification
            ? 'Jika Anda tidak membuat akun atau meminta verifikasi ini, abaikan email ini.'
            : 'Jika Anda tidak meminta perubahan ini, abaikan email ini. Password Anda tidak akan berubah selama tautan ini tidak digunakan.';
        $subject = $verification ? 'Konfirmasi email akun PENA Anda' : 'Atur ulang password akun PENA Anda';
        $text = "PENA\n\n$title\n\n$intro\n\n$action:\n$url\n\n$validity\nJangan bagikan tautan ini kepada siapa pun.\n\n$safety\n\nEmail otomatis dari PENA. Jangan membalas email ini.";
        $href = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = <<<HTML
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>$subject</title></head>
<body style="margin:0;padding:0;background-color:#F4F4F1;color:#1D1D1B;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;font-size:1px;line-height:1px;color:#F4F4F1;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">$title. $validity</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F4F4F1;">
<tr><td align="center" style="padding:32px 16px;">
<!--[if mso]><table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background-color:#FFFFFF;border:1px solid #D9D7CF;border-top:4px solid #E7B53B;">
<tr><td style="padding:28px 24px 24px;border-bottom:1px solid #D9D7CF;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
<td style="padding:7px 10px;background-color:#1D1D1B;color:#FFFFFF;font-size:18px;font-weight:700;letter-spacing:1px;">PENA</td>
<td style="padding-left:12px;color:#56554F;font-size:14px;">Surat pribadi dan organisasi</td>
</tr></table>
</td></tr>
<tr><td style="padding:32px 24px;">
<h1 style="margin:0 0 16px;font-size:26px;line-height:34px;font-weight:700;color:#1D1D1B;">$title</h1>
<p style="margin:0 0 24px;font-size:16px;line-height:26px;color:#56554F;">$intro</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="#1F3A8A" style="background-color:#1F3A8A;border-radius:4px;mso-padding-alt:15px 24px;">
<a href="$href" style="display:inline-block;padding:15px 24px;color:#FFFFFF;font-size:16px;line-height:22px;font-weight:700;text-decoration:none;border-radius:4px;">$action</a>
</td></tr></table>
<p style="margin:24px 0 0;padding:14px 16px;background-color:#F4F4F1;border-left:3px solid #E7B53B;font-size:14px;line-height:22px;color:#56554F;">$validity<br>Jangan bagikan tautan ini kepada siapa pun.</p>
<p style="margin:24px 0 8px;font-size:13px;line-height:21px;color:#56554F;">Jika tombol tidak berfungsi, salin tautan berikut ke browser Anda:</p>
<p style="margin:0;font-size:13px;line-height:21px;word-break:break-all;overflow-wrap:anywhere;"><a href="$href" style="color:#1F3A8A;text-decoration:underline;word-break:break-all;">$href</a></p>
</td></tr>
<tr><td style="padding:24px;border-top:1px solid #D9D7CF;">
<p style="margin:0 0 12px;font-size:13px;line-height:21px;color:#56554F;">$safety</p>
<p style="margin:0;font-size:12px;line-height:20px;color:#6B6A64;">Email otomatis dari PENA. Jangan membalas email ini.</p>
</td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table>
</body></html>
HTML;
        return compact('subject', 'text', 'html');
    }
}
