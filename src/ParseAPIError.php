<?php

declare(strict_types=1);

namespace ParseAPI;

/**
 * Every non-2xx response from the API. Branch on $errorCode, never on the message.
 */
class ParseAPIError extends \Exception
{
	public function __construct(
		public readonly int $status,
		public readonly string $errorCode,
		string $message,
		public readonly ?string $docs = null,
		public readonly ?string $requestId = null,
	) {
		parent::__construct($message);
	}
}
