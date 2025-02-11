<?php
declare(strict_types=1);

namespace Litalico\EgR2\Exceptions;

use LogicException;

/**
 * Exception for invalid OpenAPI definitions.
 * This exception is thrown when there is a problem with the API definition according to the OpenAPI specification.
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
        $msgJson = json_encode($messages, JSON_UNESCAPED_UNICODE);
        if ($msgJson === false) {
            $msgJson = 'Error message parsing failed';
        }
        parent::__construct($msgJson);
    }

    /**
     * @return list<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
