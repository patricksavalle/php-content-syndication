<?php

declare(strict_types=1);

namespace ContentSyndication {

    use CurlHandle;
    use Exception;

    class HttpRequest
    {
        protected CurlHandle $curl;
        protected mixed $content;
        protected mixed $httpcode;

        public function __construct(string $url)
        {
            $this->curl = curl_init();
            curl_setopt($this->curl, CURLOPT_URL, $url);
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); // follow redirects
            curl_setopt($this->curl, CURLOPT_AUTOREFERER, true); // set referer on redirect
            curl_setopt($this->curl, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36 OPR/78.0.4093.112"); // some feeds require a user agent
            curl_setopt($this->curl, CURLOPT_HEADER, false);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($this->curl, CURLOPT_ENCODING, '');
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true); // no echo, just return result
            $this->content = curl_exec($this->curl);
            if ($this->content === false) {
                throw new Exception("CURL exception on: " . $url);
            }
            $this->httpcode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            if ($this->httpcode < 200 or $this->httpcode >= 400) {
                throw new Exception("CURL http code $this->httpcode on: " . $url);
            }
        }

        public function getHttpCode(): int
        {
            return (int)$this->httpcode;
        }

        public function getContent(): string
        {
            return $this->content;
        }

        public function getEffectiveUrl(): string
        {
            return curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
        }

        public function getContentType(): string
        {
            return curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);
        }

        public function getContentLength(): int
        {
            return (int)curl_getinfo($this->curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        }

        public function __destruct()
        {
            @curl_close($this->curl);
        }
    }
}


