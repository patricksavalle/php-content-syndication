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
            $doc->loadHTML((new HttpRequest)($url));
            $xpathdom = new DOMXPath($doc);

            $xfunc = function (string $xpath) use ($xpathdom) {
                return @$xpathdom->query($xpath)->item(0)->nodeValue;
            };

            $metadata['url']
                = $xfunc('/*/head/meta[@property="og:url"]/@content')
                ?? $xfunc('/*/head/meta[@name="twitter:url"]/@content')
                ?? $xfunc('/*/head/link[@rel="canonical"]/@href')
                ?? (new Url($url))->normalized()->get();

            $metadata['title']
                = $xfunc('/*/head/meta[@property="og:title"]/@content')
                ?? $xfunc('/*/head/meta[@name="twitter:title"]/@content')
                ?? $xfunc('/*/head/title');

            $metadata['description']
                = $xfunc('/*/head/meta[@property="og:description"]/@content')
                ?? $xfunc('/*/head/meta[@name="twitter:description"]/@content')
                ?? $xfunc('/*/head/meta[@name="description"]/@content');

            // TODO can be multiple images
            $metadata['image']
                = $xfunc('//meta[@property="og:image"]/@content')
                ?? $xfunc('/*/head/meta[@name="twitter:image"]/@content')
                ?? $xfunc('/*/head/link[@rel="apple-touch-icon"]/@href');

            $metadata['site_name']
                = $xfunc('/*/head/meta[@property="og:site_name"]/@content')
                ?? $xfunc('/*/head/meta[@name="twitter:site"]/@content');

            // TODO use JSON-LD data
            // https://jsonld.com/news-article/
            // https://jsonld.com/blog-post/

            // get RSS and Atom feeds
            // TODO can be multiple feeds, for now return first
            $metadata['rss'] = $xfunc('/*/head/link[@rel="alternate"][@type="application/rss+xml"]/@href');
            $metadata['atom'] = $xfunc('/*/head/link[@rel="alternate"][@type="application/atom+xml"]/@href');

            // keywords, author, copyright
            $metadata['keywords']
                = $xfunc('/*/head/meta[@name="keywords"]/@content')
                ?? $xfunc('/*/head/meta[@name="news_keywords"]/@content');
            $metadata['author'] = $xfunc('/*/head/meta[@name="author"]/@content');
            $metadata['copyright'] = $xfunc('/*/head/meta[@name="copyright"]/@content');

            // some URL magic
            if (isset($metadata['image'])) $metadata['image'] = (new Url($metadata['image']))->absolutized($metadata['url'])->get();
            if (isset($metadata['rss'])) $metadata['rss'] = (new Url($metadata['rss']))->absolutized($metadata['url'])->get();
            if (isset($metadata['atom'])) $metadata['atom'] = (new Url($metadata['atom']))->absolutized($metadata['url'])->get();

            // return keywords as unique array, minimum clean up
            $keywords = [];
            foreach (explode(",", $metadata['keywords'] ?? "") as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    $keywords[] = $keyword;
                }
            }
            $metadata['keywords'] = array_unique($keywords);

            return $metadata;
        }
    }

}