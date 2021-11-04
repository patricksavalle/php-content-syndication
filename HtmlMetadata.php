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
            assert(filter_var($url, FILTER_VALIDATE_URL) !== false);
            $metadata = [];
            libxml_use_internal_errors(true);
            $doc = new DomDocument;
            $response = new HttpRequest($url);
            if (stripos($response->getContentType(), "text/html") !== 0) {
                throw new Exception("type must be text/html", 400);
            }
            $file = $response->getContent();
            $file = (new Text($file))->reEncode();
            $doc->loadHTML((string)$file);
            $xpathdom = new DOMXPath($doc);

            $xvalue = function (string $xpath) use ($xpathdom) {
                $return = $xpathdom->query($xpath)->item(0)->nodeValue ?? null;
                if ($return !== null) $return = (string)(new Text($return))->reEncode();
                return $return;
            };

            $metadata['url']
                = $xvalue('/*/head/meta[@property="og:url"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:url"]/@content')
                ?? $xvalue('/*/head/link[@rel="canonical"]/@href')
                ?? (string)(new Url($url))->normalized();

            $metadata['title']
                = $xvalue('/*/head/meta[@property="og:title"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:title"]/@content')
                ?? $xvalue('/*/head/title');

            $metadata['description']
                = $xvalue('/*/head/meta[@property="og:description"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:description"]/@content')
                ?? $xvalue('/*/head/meta[@name="description"]/@content');

            // TODO can be multiple images
            $metadata['image']
                = $xvalue('//meta[@property="og:image"]/@content')
                ?? $xvalue('/*/head/meta[@name="twitter:image"]/@content')
                ?? $xvalue('/*/head/link[@rel="apple-touch-icon"]/@href');

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
                ?? $xvalue('//html/@lang');
            $metadata['language'] = substr($metadata['language'] ?? "", 0, 2);

            // TODO use JSON-LD data
            // https://jsonld.com/news-article/
            // https://jsonld.com/blog-post/

            // get RSS and Atom feeds
            // TODO can be multiple feeds, for now return first
            $metadata['rss'] = $xvalue('/*/head/link[@rel="alternate"][@type="application/rss+xml"]/@href');
            $metadata['atom'] = $xvalue('/*/head/link[@rel="alternate"][@type="application/atom+xml"]/@href');

            // keywords, author, copyright
            $metadata_keywords =
                $xvalue('/*/head/meta[@name="keywords"]/@content') . "," .
                $xvalue('/*/head/meta[@name="news_keywords"]/@content');
            $metadata['author'] = $xvalue('/*/head/meta[@name="author"]/@content');
            $metadata['copyright'] = $xvalue('/*/head/meta[@name="copyright"]/@content');

            // some URL magic
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