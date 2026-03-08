<?php

namespace App\Services\SessionResolver;

use RuntimeException;

class ActiveSessionConflict extends RuntimeException
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly ?int $inboxId
    ) {
        parent::__construct('Active session already exists.');
    }
}
