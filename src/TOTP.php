<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;

// Dummy QR provider — we generate QR codes client-side
class NullQRProvider implements IQRCodeProvider
{
    public function getQRCodeImage(string $qrText, int $size): string
    {
        return '';
    }

    public function getMimeType(): string
    {
        return 'image/png';
    }
}

class TOTP
{
    private TwoFactorAuth $tfa;

    private string $issuer;

    public function __construct()
    {
        $this->issuer = Settings::get('totp_issuer', 'ZT-Portal');
        $this->tfa = new TwoFactorAuth(
            qrcodeprovider: new NullQRProvider(),
            issuer: $this->issuer,
            digits: 6,
            period: 30
        );
    }

    public function generateSecret(): string
    {
        return $this->tfa->createSecret();
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->tfa->verifyCode($secret, $code, 1); // ±1 window drift
    }

    public function getQRCodeUrl(string $issuer, string $username, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($username),
            $secret,
            rawurlencode($issuer)
        );
    }
}
