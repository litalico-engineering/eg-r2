<?php
declare(strict_types=1);

namespace Litalico\EgR2\Exceptions;

use LogicException;

/**
 * OpenAPI-defined error Exception
 * @package Litalico\EgR2\Exceptions
 */
class InvalidOpenApiDefinitionException extends LogicException
{
    /**
     * @param list<string> $messages error message array
     */
    public function __construct(
        protected array $messages
    ) {
        parent::__construct('');
    }

    /**
     * @return list<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
