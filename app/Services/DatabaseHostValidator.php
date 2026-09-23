<?php

namespace App\Services;

class DatabaseHostValidator
{
    /**
     * Determine if a target database host is permitted for connection.
     * Rejects loopback, link-local, RFC1918 private subnets, reserved ranges,
     * cloud metadata hosts/addresses, and characters that could lead to DSN injection.
     *
     * @param  string  $rawHost  Raw host name or IP address provided by the user.
     * @param  string|null  $resolvedIp  Populated with the resolved IPv4/IPv6 address if permitted.
     * @return bool True if permitted and safe; false otherwise.
     */
    public static function isPermitted(string $rawHost, ?string &$resolvedIp = null): bool
    {
        $resolvedIp = null;
        $host = strtolower(trim($rawHost));

        if ($host === '' || ! preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            return false;
        }

        // Explicit blocklist for cloud metadata endpoints across AWS, GCP, Azure, and Alibaba
        $metadataHosts = [
            '169.254.169.254',
            'metadata.google.internal',
            'instance-data',
            '100.100.100.200',
        ];

        if (in_array($host, $metadataHosts, true)) {
            return false;
        }

        // Single resolution to prevent DNS rebinding TOCTOU vulnerabilities
        $resolved = gethostbyname($host);

        if ($resolved === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            // Unresolvable hostname
            return false;
        }

        if (in_array($resolved, ['169.254.169.254', '100.100.100.200'], true)
            || str_starts_with($resolved, '169.254.')) {
            return false;
        }

        // Deny private, reserved, and loopback IP address ranges
        if (filter_var($resolved, FILTER_VALIDATE_IP) === false
            || filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // PHP's filters do not cover RFC 6598 shared address space (100.64.0.0/10),
        // used for carrier-grade NAT and cloud-internal service ranges (e.g. the
        // Alibaba 100.100.100.200 metadata endpoint singled out above), nor several
        // other special-use IPv4 blocks. Deny them explicitly so the guard stays
        // deny-by-default for non-public address space.
        $packed = @inet_pton($resolved);
        if ($packed !== false && strlen($packed) === 4) {
            $first = ord($packed[0]);
            $second = ord($packed[1]);
            $third = ord($packed[2]);

            $isSpecialUse = ($first === 100 && $second >= 64 && $second <= 127) // RFC 6598 shared address space
                || ($first === 192 && $second === 0 && $third === 0)           // RFC 6890 IETF protocol assignments
                || ($first === 192 && $second === 0 && $third === 2)           // TEST-NET-1
                || ($first === 192 && $second === 88 && $third === 99)         // 6to4 relay anycast
                || ($first === 198 && ($second === 18 || $second === 19))      // RFC 2544 benchmarking
                || ($first === 198 && $second === 51 && $third === 100)        // TEST-NET-2
                || ($first === 203 && $second === 0 && $third === 113)         // TEST-NET-3
                || ($first >= 224 && $first <= 239);                           // multicast

            if ($isSpecialUse) {
                return false;
            }
        }

        $resolvedIp = $resolved;

        return true;
    }
}
