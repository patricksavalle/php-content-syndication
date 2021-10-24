<?php

declare(strict_types=1);

namespace ContentSyndication {

    class ArchiveOrg
    {
        /** @noinspection PhpUnusedParameterInspection */
        static public function original(string $url, bool $follow_redirects = false)
        {
            assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
            // HEAD request
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true); // set to HEAD request
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // don't output the response
            curl_exec($ch);
            $result = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($result >= 200 and $result < 400) ? $url : false;
        }

        static public function closest(string $url)
        {
            assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
            $tmp = "https://archive.org/wayback/available?url=$url";
            $json = json_decode((new HttpRequest)($tmp));
            return $json->archived_snapshots->closest->url ?? false;
        }

        static public function archive(string $url): string
        {
            assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
            // store in webarchive.com and get newly archived url back
            $url = "https://web.archive.org/save/$url";
            (new HttpRequest)($url);
            return $url;
        }

        static public function archiveAsync(string $url)
        {
            assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
            // store in webarchive.com and get newly archived url back
            (new HttpFireAndForgetRequest)("https://web.archive.org/save/$url");
        }
    }
}