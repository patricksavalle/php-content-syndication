<?php

declare(strict_types=1);

namespace ContentSyndication {

    use Parsedown;

    class ParsedownExt extends Parsedown
    {
        protected $processor;

        public function __construct(callable $ElementProcessor = null)
        {
            $this->processor = $ElementProcessor;
        }

        /** @noinspection PhpMissingReturnTypeInspection */
        protected function element(array $Element)
        {
            if (is_callable($this->processor)) {
                $Element = call_user_func($this->processor, $Element);
            }
            return parent::element($Element);
        }
    }
}
