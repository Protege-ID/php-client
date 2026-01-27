<?php

namespace ProtegeId\DataObjects;

final class ProtegeIdSession
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $temporaryUrl,
        public readonly string $userRef,
        public readonly ?string $expiresAt = null
    ) {
    }
}
