<?php

declare(strict_types=1);

namespace ContentSyndication {

    class Url
    {
        private $url;

        static private $campaign_parameters = [
            "dgcid",
            "utm_",
            "__twitter_impression",
        ];

        public function __construct(string $url)
        {
            $this->url = $url;
        }

        public function get(): string
        {
            return $this->url;
        }

        /*
         * Try to construct a normalized / canonical URL.
         * This will avoid different URL's to the same resource in the system.
         *
         *  Borrowed from the FLATTR code. Ported tot PHP 7.3 and optimized.
         */

        public function normalized(): Url
        {
            // -------------------------------------------------------
            //
            // -------------------------------------------------------

            $newUrl = "";
            $url = parse_url($this->url);
            $defaultSchemes = array("http" => 80, "https" => 443);

            if (isset($url['scheme'])) {
                $url['scheme'] = strtolower($url['scheme']);
                // Strip scheme default ports
                if (isset($defaultSchemes[$url['scheme']]) && isset($url['port']) && $defaultSchemes[$url['scheme']] === $url['port'])
                    unset($url['port']);
                $newUrl .= $url['scheme'] . "://";
            } else {
                $newUrl .= "https://";
            }

            if (isset($url['host'])) {
                $url['host'] = strtolower($url['host']);
                // Seems like a valid domain, properly validation should be made in higher layers.
                if (preg_match("/[a-z]+\Z/", $url['host'])) {
                    if (preg_match("/^www\./", $url['host']) && gethostbyname($url['host']) === gethostbyname(str_replace("www.", "", $url['host'])))
                        $newUrl .= str_replace("www.", "", $url['host']);
                    else
                        $newUrl .= $url['host'];
                } else
                    $newUrl .= $url['host'];
            }

            if (isset($url['port']))
                $newUrl .= ":{$url['port']}";

            if (isset($url['path'])) {
                // Case normalization
                $url['path'] = preg_replace_callback(
                    '/(%([0-9abcdef][0-9abcdef]))/x',
                    function ($matches) {
                        return '%' . strtoupper($matches[1]);
                    },
                    $url['path']);
                //Strip duplicate slashes
                while (preg_match("/^(?!https?:)\/\//", $url['path']))
                    $url['path'] = preg_replace("/^(?!https?:)\/\//", "/", $url['path']);

                /*
                 * Decode unreserved characters, http://www.apps.ietf.org/rfc/rfc3986.html#sec-2.3
                 * Heavily rewritten version of urlDecodeUnreservedChars() in Glen Scott's url-normalizer.
                 */

                $u = array();
                for ($o = 65; $o <= 90; $o++)
                    $u[] = dechex($o);
                for ($o = 97; $o <= 122; $o++)
                    $u[] = dechex($o);
                for ($o = 48; $o <= 57; $o++)
                    $u[] = dechex($o);
                $chrs = array('-', '.', '_', '~');
                foreach ($chrs as $chr)
                    $u[] = dechex(ord($chr));
                $url['path'] = preg_replace_callback(
                    array_map(
                        function ($str) {
                            return "/%" . strtoupper($str) . "/x";
                        }, $u),
                    function ($matches) {
                        return chr(hexdec($matches[0]));
                    }, $url['path']);
                // Remove directory index
                $defaultIndexes = array("/default\.aspx/" => "default.aspx", "/default\.asp/" => "default.asp",
                    "/index\.html/" => "index.html", "/index\.htm/" => "index.htm",
                    "/default\.html/" => "default.html", "/default\.htm/" => "default.htm",
                    "/index\.php/" => "index.php", "/index\.jsp/" => "index.jsp");
                foreach ($defaultIndexes as $index => $strip) {
                    if (preg_match($index, $url['path']))
                        $url['path'] = str_replace($strip, "", $url['path']);
                }

                /**
                 * Path segment normalization, http://www.apps.ietf.org/rfc/rfc3986.html#sec-5.2.4
                 * Heavily rewritten version of removeDotSegments() in Glen Scott's url-normalizer.
                 */

                $new_path = '';
                while (!empty($url['path'])) {
                    if (preg_match('!^(\.\./|\./)!x', $url['path']))
                        $url['path'] = preg_replace('!^(\.\./|\./)!x', '', $url['path']);
                    elseif (preg_match('!^(/\./)!x', $url['path'], $matches) || preg_match('!^(/\.)$!x', $url['path'], $matches))
                        $url['path'] = preg_replace("!^" . $matches[1] . "!", '/', $url['path']);
                    elseif (preg_match('!^(/\.\./|/\.\.)!x', $url['path'], $matches)) {
                        $url['path'] = preg_replace('!^' . preg_quote($matches[1], '!') . '!x', '/', $url['path']);
                        $new_path = preg_replace('!/([^/]+)$!x', '', $new_path);
                    } elseif (preg_match('!^(\.|\.\.)$!x', $url['path']))
                        $url['path'] = preg_replace('!^(\.|\.\.)$!x', '', $url['path']);
                    else {
                        if (preg_match('!(/*[^/]*)!x', $url['path'], $matches)) {
                            $first_path_segment = $matches[1];
                            $url['path'] = preg_replace('/^' . preg_quote($first_path_segment, '/') . '/', '', $url['path'], 1);
                            $new_path .= $first_path_segment;
                        }
                    }
                }
                $newUrl .= rtrim($new_path, '/');
            }

            unset($url['fragment']);

            // Sort GET params alphabetically
            if (isset($url['query'])) {
                if (preg_match("/&/", $url['query'])) {
                    $s = explode("&", $url['query']);
                    // remove the campaign arguments
                    foreach ($s as $i => $z) {
                        foreach (static::$campaign_parameters as $key) {
                            if (stripos($z, $key) === 0) unset($s[$i]);
                        }
                    }
                    if (!empty($s)) {
                        $url['query'] = "";
                        sort($s);
                        foreach ($s as $z)
                            $url['query'] .= "$z&";
                        $url['query'] = preg_replace("/&\Z/", "", $url['query']);
                        $newUrl .= "?{$url['query']}";
                    }
                }
            }

            $this->url = $newUrl;
            return $this;
        }

        // ---------------------------------------------------------------------------

        function absolutized($base): Url
        {
            // -------------------------------
            // return if already absolute URL
            // -------------------------------

            $scheme = parse_url($this->url, PHP_URL_SCHEME);
            if (!empty($scheme)) {
                return $this;
            }

            // ------------------------------------------------
            // parse base URL and convert to local variables:
            //   $scheme, $host, $path */
            // ------------------------------------------------

            $parts = parse_url($base);
            $scheme = $parts['scheme'] ?? null;
            $host = $parts['host'] ?? null;
            $path = $parts['path'] ?? null;

            assert(empty($port));
            assert(empty($user));
            assert(empty($pass));

            if (strpos($this->url, "//") === 0) {
                // assume https:
                $this->url = $scheme . ":" . $this->url;
                return $this;
            }

            // ---------------------
            // queries and anchors
            // ---------------------

            if (strpos($this->url, '#') === 0 || strpos($this->url, '?') === 0) {
                $this->url = $base . $this->url;
                return $this;
            }

            // ------------------------------------------
            // remove non-directory element from path
            // ------------------------------------------

            $path = preg_replace('#/[^/]*$#', '', $path);

            // ------------------------------------------
            // destroy path if relative url points to root
            // ------------------------------------------

            if ($this->url[0] === '/') {
                $path = '';
            }

            // ------------------------------------------
            // dirty absolute URL
            // ------------------------------------------

            $abs = "$host$path/$this->url";

            // -------------------------------------------------
            // replace '//' or '/./' or '/foo/../' with '/' */
            // -------------------------------------------------

            $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) ;

            // ------------------------
            // absolute URL is ready!
            // ------------------------

            $this->url = "$scheme://$abs";
            return $this;
        }
    }
}