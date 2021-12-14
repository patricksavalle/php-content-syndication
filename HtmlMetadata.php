<?php

declare(strict_types=1);

namespace ContentSyndication {

    use DOMDocument;
    use DOMXPath;
    use Exception;

    class HtmlMetadata
    {
        /**
         * Normalize URL's so different URL's to the same URI can be compared
         *
         * @throws Exception
         */
        function __invoke(string $url): array
        {
            $httpRequest = function ($url): array {
                assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
                $response = new HttpRequest($url);
                if (stripos($response->getContentType(), "text/html") !== 0) {
                    throw new Exception("type must be text/html", 400);
                }
                $metadata = $this->extractMetadata($response->getContent(), $url);
                if (empty($metadata["description"])) {
                    throw new Exception("no metadata found", 400);
                }
                return $metadata;
            };

            try {

                // first try original URL
                $metadata = $httpRequest($url);

            } catch (Exception $e) {

                // if blocked or failed, misuse archive.org as crawler
                error_log("retrying metadata-inspection on ($url) because: " . $e->getMessage());
                $metadata = $httpRequest(ArchiveOrg::archive($url));
            }
            return $metadata;
        }

        protected function extractMetadata(string $file, string $url): array
        {
            $file = (new Text($file))->reEncode();
            libxml_use_internal_errors(true);
            $doc = new DomDocument;
            $doc->loadHTML((string)$file);
            $xpathdom = new DOMXPath($doc);

            $xvalue = function (string $xpath) use ($xpathdom) {
                $return = $xpathdom->query($xpath)->item(0)->nodeValue ?? null;
                if ($return !== null) $return = (string)(new Text($return))->reEncode();
                return $return;
            };

            $jsonld = json_decode($xvalue('//script[@type="application/ld+json"]/text()') ?? "", true);

            $metadata['url']
                = $xvalue('/*/head/link[@rel="canonical"]/@href')
                ?? $xvalue('/*/head/meta[@name="twitter:url"]/@content')
                ?? $xvalue('/*/head/meta[@property="og:url"]/@content')
                ?? $jsonld['url']
                ?? (string)(new Url($url))->normalized();

            $metadata['title']
                = $xvalue('/*/head/meta[@property="og:title"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:title"]/@content')
                ?? $xvalue('/*/head/meta[@name="DC:Title"]/@content')
                ?? $xvalue('/*/head/title')
                ?? $jsonld['headline']
                ?? null;

            $metadata['description']
                = $xvalue('/*/head/meta[@property="DC:Description"]/@content')
                ?? $xvalue('/*/head/meta[@property="og:description"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:description"]/@content')
                ?? $jsonld['description']
                ?? $xvalue('/*/head/meta[@name="description"]/@content')
                ?? null;

            // TODO can be multiple images, for now return first
            $metadata['image']
                = $xvalue('//meta[@property="og:image"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:image"]/@content')
                ?? $jsonld['image'][0] ?? $jsonld['image'] ?? null;

            $metadata['video']
                = $xvalue('//meta[@property="og:video"]/@content');

            $metadata['video:type']
                = $xvalue('//meta[@property="og:video:type"]/@content');

            $metadata['video:release_date']
                = $xvalue('//meta[@property="og:video:release_date"]/@content');

            $metadata['video:duration']
                = $xvalue('//meta[@property="og:video:duration"]/@content');

            $metadata['video:series']
                = $xvalue('//meta[@property="og:video:series"]/@content');

            $metadata['site_name']
                = $xvalue('/*/head/meta[@property="og:site_name"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:site"]/@content');

            $metadata['language']
                = $xvalue('/*/head/meta[@http-equiv="content-language"]/@content')
                ?? $xvalue('/*/head/meta[@name="DC:Language"]/@content')
                ?? $xvalue('//html/@lang');
            $metadata['language'] = substr($metadata['language'] ?? "", 0, 2);

            // get RSS and Atom feeds
            // TODO can be multiple feeds, for now return first
            $metadata['rss'] = $xvalue('/*/head/link[@rel="alternate"][@type="application/rss+xml"]/@href');
            $metadata['atom'] = $xvalue('/*/head/link[@rel="alternate"][@type="application/atom+xml"]/@href');

            // keywords, author, copyright
            $metadata_keywords
                = $xvalue('/*/head/meta[@name="keywords"]/@content')
                . ","
                . $xvalue('/*/head/meta[@name="news_keywords"]/@content');
            $metadata['author']
                = $xvalue('/*/head/meta[@name="author"]/@content')
                ?? $jsonld['author'] ?? $jsonld['editor'] ?? null;
            $metadata['copyright'] = $xvalue('/*/head/meta[@name="copyright"]/@content');

            // some URL magic
            if (filter_var($metadata['url'], FILTER_VALIDATE_URL) === false) {
                $metadata['url'] = $url;
            }
            if (isset($metadata['image'])) $metadata['image'] = (string)(new Url($metadata['image']))->absolutized($metadata['url']);
            if (isset($metadata['video'])) $metadata['video'] = (string)(new Url($metadata['video']))->absolutized($metadata['url']);
            if (isset($metadata['rss'])) $metadata['rss'] = (string)(new Url($metadata['rss']))->absolutized($metadata['url']);
            if (isset($metadata['atom'])) $metadata['atom'] = (string)(new Url($metadata['atom']))->absolutized($metadata['url']);

            // return keywords as unique array, minimum clean up
            $metadata['keywords'] = [];
            foreach (explode(",", $metadata_keywords) as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword) and !in_array($keyword, $metadata['keywords'])) {
                    $metadata['keywords'][] = $keyword;
                }
            }

            return $metadata;
        }
    }
}