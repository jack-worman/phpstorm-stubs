<?php

namespace StubTests\Framework\Validator\Services;

use StubTests\Framework\Runner\PhpVersions;
use StubTests\Framework\Storage\StubDataQueryInterface;
use StubTests\Framework\Validator\Contracts\ReflectionProviderInterface;

/**
 * Finds the newest PHP version whose reflection still has an entity.
 *
 * Value checks compare at the latest version only, because values legitimately change between
 * releases and a stub holds one. An entity PHP removed before then would never be compared, so
 * those checks also compare at the last version that has it.
 */
final class LastReflectedVersionService
{
    public function __construct(private readonly ReflectionProviderInterface $reflectionProvider) {}

    /**
     * @param callable(StubDataQueryInterface): bool $exists
     */
    public function isLastVersionWith(string $phpVersion, callable $exists): bool
    {
        $isLater = false;
        foreach (PhpVersions::cases() as $version) {
            if ($isLater && $exists($this->reflectionProvider->getReflection($version->value))) {
                return false;
            }
            $isLater = $isLater || $version->value === $phpVersion;
        }

        return true;
    }
}
