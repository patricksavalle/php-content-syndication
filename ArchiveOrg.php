<?php /** @noinspection HttpUrlsUsage */

declare(strict_types=1);

namespace ContentSyndication {

    use Exception;

    class ArchiveOrg
    {
        /** @noinspection PhpUnusedParameterInspection */
        static public function original(string $url, bool $follow_redirects = false)
        {
            // HEAD request
            $ch = curl_init();
            try {
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_NOBODY, true); // set to HEAD request
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // don't output the response
                curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36 OPR/78.0.4093.112"); // some feeds require a user agent
                curl_exec($ch);
                $result = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            } finally {
                curl_close($ch);
            }
            // do NOT trust redirects or moved to
            return ($result === 200) ? $url : false;
        }

        /** @noinspection PhpMissingReturnTypeInspection */
        static public function closest(string $url)
        {
            $response = new HttpRequest("https://archive.org/wayback/available?url=$url");
            $json = json_decode($response->getContent());
            return $json->archived_snapshots->closest->url ?? false;
        }

        static public function originalOrClosest(string $url): string
        {
            /** @noinspection PhpForeachOverSingleElementArrayLiteralInspection */
            foreach ([
                         "https://t.me",
                     ] as $bypass) {
                if (stripos($url, $bypass) === 0) return $url;
            }
            try {
                $original = ArchiveOrg::original($url);
                if ($original !== false) {
                    return $original;
                }
                $closest = ArchiveOrg::closest($url);
                if ($closest !== false) {
                    return $closest;
                }
            } catch (Exception $e) {
                // ignore
            }
            // last resort, just return origina;
            return $url;
        }

        static public function archive(string $url): string
        {
            assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
            // store in webarchive.com and get newly archived url back
            return (new HttpRequest("https://web.archive.org/save/$url"))->getEffectiveUrl();
        }

        static public function originalUrl(string $url): string
        {
            return preg_match("@https?:\/\/web\.archive\.org\/web\/\d+\/(?<archivedurl>.*)@", $url, $matches) === 1
                ? $matches["archivedurl"]
                : $url;
        }

        static public function archiveAsync(string $url)
        {
            assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
            // store in webarchive.com and get newly archived url back
            (new HttpFireAndForgetRequest)("https://web.archive.org/save/$url");
        }
    }
}