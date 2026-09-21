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

        $resolvedIp = $resolved;

        return true;
    }
}
