<?php /** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ContentSyndication {

    use Exception;
    use SimpleXMLElement;

    /**
     * Usage: $feed_elements = (new XmlFeed)($url);
     */

    class XmlFeed
    {
        protected $xml;

        public function __invoke(string $url, string $user = "", string $pass = ""): array
        {
            $xml = self::loadXml($url, $user, $pass);
            if ($xml->channel) {
                return self::fromRss($xml)->toArray();
            } else {
                return self::fromAtom($xml)->toArray();
            }
        }

        public static function loadRss(string $url, string $user = "", string $pass = ""): XmlFeed
        {
            return self::fromRss(self::loadXml($url, $user, $pass));
        }

        public static function loadAtom(string $url, string $user = "", string $pass = ""): XmlFeed
        {
            return self::fromAtom(self::loadXml($url, $user, $pass));
        }

        private static function fromRss(SimpleXMLElement $xml): XmlFeed
        {
            if (!$xml->channel) {
                throw new Exception('Invalid feed.');
            }

            self::adjustNamespaces($xml);

            foreach ($xml->channel->item as $item) {
                // converts namespaces to dotted tags
                self::adjustNamespaces($item);

                // generate 'timestamp' tag
                if (isset($item->{'dc:date'})) {
                    $item->timestamp = strtotime((string)$item->{'dc:date'});
                } elseif (isset($item->pubDate)) {
                    $item->timestamp = strtotime((string)$item->pubDate);
                }
            }
            $feed = new self;
            $feed->xml = $xml->channel;
            return $feed;
        }

        private static function fromAtom(SimpleXMLElement $xml): XmlFeed
        {
            /** @noinspection HttpUrlsUsage */
            if (!in_array('http://www.w3.org/2005/Atom', $xml->getDocNamespaces(), true)
                && !in_array('http://purl.org/atom/ns#', $xml->getDocNamespaces(), true)
            ) {
                throw new Exception('Invalid feed.');
            }

            // generate 'timestamp' tag
            foreach ($xml->entry as $entry) {
                $entry->timestamp = strtotime((string)$entry->updated);
            }
            $feed = new self;
            $feed->xml = $xml;
            return $feed;
        }

        public function __get($name)
        {
            return $this->xml->{$name};
        }

        public function toArray(SimpleXMLElement $xml = null)
        {
            if ($xml === null) {
                $xml = $this->xml;
            }

            if (!$xml->children()) {
                return (string)$xml;
            }

            $arr = [];
            foreach ($xml->children() as $tag => $child) {
                if (count($xml->$tag) === 1) {
                    $arr[$tag] = $this->toArray($child);
                } else {
                    $arr[$tag][] = $this->toArray($child);
                }
            }

            return $arr;
        }

        private static function loadXml(string $url, string $user, string $pass): SimpleXMLElement
        {
            $data = trim((new HttpRequest)($url, $user, $pass));
            return new SimpleXMLElement($data, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOCDATA);
        }

        private static function adjustNamespaces(SimpleXMLElement $el)
        {
            foreach ($el->getNamespaces(true) as $prefix => $ns) {
                $children = $el->children($ns);
                foreach ($children as $tag => $content) {
                    $el->{$prefix . ':' . $tag} = $content;
                }
            }
        }
    }
}