<?php

declare(strict_types=1);

namespace ContentSyndication {

    /**
     * Usage: $content = (new HttpRequest)($url);
     */
    class HttpFireAndForgetRequest
    {
        public function __invoke(string $url): bool
        {
            $socket = false;
            $return = false;
            try {
                $urlParts = parse_url($url);
                $urlParts['path'] = $urlParts['path'] ?? '/';
                $urlParts['port'] = $urlParts['port'] ?? $urlParts['scheme'] === 'https' ? 443 : 80;
                $request = "GET {$urlParts['path']} HTTP/1.1\r\n";
                $request .= "Host: {$urlParts['host']}\r\n";
                $request .= "Connection: Close\r\n";
                $request .= "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36 OPR/78.0.4093.112\r\n";
                $prefix = substr($url, 0, 8) === 'https://' ? 'tls://' : '';
                $socket = fsockopen($prefix . $urlParts['host'], $urlParts['port']);
                if ($socket !== false) $return = fwrite($socket, $request);
            } finally {
                if ($socket !== false) fclose($socket);
            }
            return $return !== false;
        }
    }
}


