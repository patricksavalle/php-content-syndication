<?php

declare(strict_types=1);

namespace ContentSyndication {

    use Exception;

    /**
     * Usage: $content = (new HttpRequest)($url);
     */
    class HttpRequest
    {
        public function __invoke(string &$url, string $user = "", string $pass = ""): string
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            if (!empty($user) or !empty($pass)) {
                curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass");
            }
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // follow redirects
            curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // set referer on redirect
            curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36 OPR/78.0.4093.112"); // some feeds require a user agent
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 20);
            curl_setopt($curl, CURLOPT_ENCODING, '');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // no echo, just return result
            if (!ini_get('open_basedir')) {
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // sometime is useful :)
            }
            $result = curl_exec($curl);
            if ($result === false or curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
                throw new Exception("CURL exception on: " . $url);
            }
            // return redirected url
            $url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
            return $result;
        }
    }
}
