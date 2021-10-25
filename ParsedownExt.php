<?php

declare(strict_types=1);

namespace ContentSyndication {

    class ParsedownExt extends \Parsedown
    {
        // Add target to links
        protected function element(array $Element)
        {
            $Element['attributes']['target'] = '_blank';
            return parent::element($Element);
        }
    }
}
