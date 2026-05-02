<?php

namespace App\Services;

class SslConverterService
{
    /**
     * Convert PEM Certificate and Private Key to PFX (PKCS#12)
     */
    public function toPfx(string $certContent, string $keyContent, string $password): string
    {
        $cert = openssl_x509_read($certContent);
        $key = openssl_pkey_get_private($keyContent, $password);

        if (!$cert || !$key) {
            throw new \Exception('Invalid certificate or private key.');
        }

        $pfxContent = '';
        if (!openssl_pkcs12_export($cert, $pfxContent, $key, $password)) {
            throw new \Exception('Failed to export to PFX: ' . openssl_error_string());
        }

        return $pfxContent;
    }

    /**
     * Extract PEM Certificate and Private Key from PFX (PKCS#12)
     */
    public function fromPfx(string $pfxContent, string $password): array
    {
        $results = [];
        if (!openssl_pkcs12_read($pfxContent, $results, $password)) {
            throw new \Exception('Failed to read PFX: ' . openssl_error_string());
        }

        return [
            'cert' => $results['cert'] ?? '',
            'key' => $results['pkey'] ?? '',
            'chain' => $results['extracerts'] ?? [],
        ];
    }

    /**
     * Convert PEM Certificate to DER format
     */
    public function pemToDer(string $pemContent): string
    {
        // Remove headers and footers
        $begin = '-----BEGIN CERTIFICATE-----';
        $end = '-----END CERTIFICATE-----';
        
        $pos1 = strpos($pemContent, $begin);
        $pos2 = strpos($pemContent, $end);
        
        if ($pos1 === false || $pos2 === false) {
            // Check if it's already DER or missing headers
            if (str_contains($pemContent, '-----BEGIN')) {
                 throw new \Exception('Invalid PEM format.');
            }
            // Fallback: try to read it as x509 anyway
        } else {
            $pemContent = substr($pemContent, $pos1 + strlen($begin), $pos2 - $pos1 - strlen($begin));
        }

        $der = base64_decode(trim($pemContent));
        if (!$der) {
            throw new \Exception('Failed to decode base64 content.');
        }

        return $der;
    }

    /**
     * Convert DER Certificate to PEM format
     */
    public function derToPem(string $derContent): string
    {
        $pem = "-----BEGIN CERTIFICATE-----\n";
        $pem .= chunk_split(base64_encode($derContent), 64, "\n");
        $pem .= "-----END CERTIFICATE-----\n";
        
        return $pem;
    }

    /**
     * Convert PEM Private Key to DER format (PKCS#8)
     */
    public function keyPemToDer(string $pemContent, ?string $password = null): string
    {
        $key = openssl_pkey_get_private($pemContent, $password);
        if (!$key) {
             throw new \Exception('Invalid private key.');
        }

        $details = openssl_pkey_get_details($key);
        if (!$details || !isset($details['key'])) {
             throw new \Exception('Could not get key details.');
        }

        // openssl_pkey_get_details returns PEM string for 'key'
        // To get DER, we have to strip PEM headers
        $pem = $details['key'];
        $pem = preg_replace('/-----BEGIN [A-Z ]+-----/', '', $pem);
        $pem = preg_replace('/-----END [A-Z ]+-----/', '', $pem);
        
        return base64_decode(trim($pem));
    }
}
