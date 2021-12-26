<?php

namespace ContentSyndication {

    use Exception;

    class Mimetype
    {
        public function getMimetype(string $url): string
        {
            $curl = curl_init();
            try {
                // HEAD request to check mimetype
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_NOBODY, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // follow redirects
                curl_setopt($curl, CURLOPT_AUTOREFERER, true); // set referer on redirect
                curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36 OPR/78.0.4093.112"); // some feeds require a user agent
                curl_exec($curl);
                if (($errno = curl_errno($curl)) !== 0) {
                    throw new Exception(curl_strerror($errno), 400);
                }
                if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
                    throw new Exception("URL not found", 400);
                }
                $contenttype = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
                if ($contenttype === false) {
                    throw new Exception("CURLINFO_CONTENT_TYPE error");
                }
                preg_match("/(?<mimetype>[^;]+)/", $contenttype, $matches);
                return $matches["mimetype"];
            } finally {
                curl_close($curl);
            }
        }

        function __invoke(string $url): string
        {
            // Mangle function signature and try to get from cache
            $cache_key = hash('md5', __METHOD__ . $url);
            $result = apcu_fetch($cache_key);
            if ($result === false) {
                // if not, call the function and cache result
                $result = $this->GetMimetype($url);
                if (apcu_add($cache_key, $result, 60 * 60) === false) {
                    error_log("APCu error on method: " . __METHOD__);
                }
            }
            return $result;
        }
    }
}