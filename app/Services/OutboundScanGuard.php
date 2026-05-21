<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutboundScanGuard
{
    public function assertAllowed(string $target, string $field = 'url'): array
    {
        $host = $this->hostFromTarget($target);

        if ($host === null || $host === '') {
            throw ValidationException::withMessages([$field => 'Enter a valid outbound scan target.']);
        }

        $host = rtrim(Str::lower($host), '.');

        if ($this->domainIsAllowed($host)) {
            return ['host' => $host, 'ips' => []];
        }

        $ips = $this->resolvedIps($host);

        if ($ips === []) {
            throw ValidationException::withMessages([$field => 'Outbound scan target could not be resolved.']);
        }

        foreach ($ips as $ip) {
            if ($this->ipIsAllowed($ip)) {
                continue;
            }

            if ($this->ipIsDenied($ip)) {
                throw ValidationException::withMessages([$field => 'Outbound scan target resolves to a private or reserved IP address.']);
            }
        }

        return ['host' => $host, 'ips' => $ips];
    }

    private function hostFromTarget(string $target): ?string
    {
        $target = trim($target);
        $url = str_contains($target, '://') ? $target : 'https://'.$target;

        return parse_url($url, PHP_URL_HOST) ?: null;
    }

    private function domainIsAllowed(string $host): bool
    {
        foreach ((array) config('security.outbound_scans.allowed_scan_domains', []) as $domain) {
            $domain = rtrim(Str::lower((string) $domain), '.');

            if ($domain !== '' && ($host === $domain || Str::endsWith($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    private function resolvedIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        foreach (dns_get_record($host, DNS_A + DNS_AAAA) ?: [] as $record) {
            foreach (['ip', 'ipv6'] as $key) {
                if (! empty($record[$key]) && filter_var($record[$key], FILTER_VALIDATE_IP)) {
                    $ips[] = $record[$key];
                }
            }
        }

        foreach (gethostbynamel($host) ?: [] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    private function ipIsAllowed(string $ip): bool
    {
        foreach ((array) config('security.outbound_scans.allowed_scan_cidrs', []) as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function ipIsDenied(string $ip): bool
    {
        if (! (bool) config('security.outbound_scans.block_private_ips', true)) {
            return false;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        foreach ((array) config('security.outbound_scans.denied_scan_cidrs', []) as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/') || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        if (! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }
}
