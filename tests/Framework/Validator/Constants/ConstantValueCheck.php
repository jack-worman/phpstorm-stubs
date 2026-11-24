<?php

namespace StubTests\Framework\Validator\Constants;

use StubTests\Framework\Storage\StubDataQueryInterface;
use StubTests\Framework\Validator\AbstractReflectionCheck;
use StubTests\Framework\Validator\Contracts\CheckResultSet;
use StubTests\Framework\Validator\Contracts\ReflectionProviderInterface;
use StubTests\Framework\Validator\KnownProblems\CheckType;
use StubTests\Framework\Validator\KnownProblems\EntityType;
use StubTests\Framework\Validator\KnownProblemsRegistry;
use StubTests\Framework\Validator\Services\ConstantValueComparator;
use StubTests\Framework\Validator\Services\EntityLookupService;
use StubTests\Framework\Validator\Services\LastReflectedVersionService;

/**
 * Validates that the values of global constants in stubs match reflection.
 *
 * Value comparison is limited to the last PHP version that has the constant: the latest one, or
 * the one before PHP removed it. Earlier versions would only report historical value changes.
 *
 * For each constant identified by $entityId the validator:
 * 1. Skips comparison unless no later version has the constant.
 * 2. Looks up the constant in reflection by ID.
 * 3. Looks up the constant in stubs by ID.
 * 4. If not found in either, silently succeeds — ConstantExistsCheck handles existence.
 * 5. Skips if either value is null (complex/dynamic expressions cannot be compared).
 * 6. Skips resource values stored as 'PHPSTORM_RESOURCE' by the reflection parser.
 * 7. Reports a failure if the values or their types differ (see ConstantValueComparator).
 *
 * Known problems are supported via EntityType::GLOBAL_CONSTANT + constantId + 'ConstantValueCheck'.
 */
class ConstantValueCheck extends AbstractReflectionCheck
{
    private EntityLookupService $entityLookup;
    private LastReflectedVersionService $lastReflectedVersion;

    public function __construct(
        ?ReflectionProviderInterface $reflectionProvider = null,
        ?KnownProblemsRegistry $knownProblemsRegistry = null,
        ?EntityLookupService $entityLookup = null
    ) {
        parent::__construct($reflectionProvider, $knownProblemsRegistry);
        $this->entityLookup = $entityLookup ?? new EntityLookupService();
        $this->lastReflectedVersion = new LastReflectedVersionService($this->reflectionProvider);
    }

    public function supports(string $phpVersion): bool
    {
        return true;
    }

    public function run(StubDataQueryInterface $stubs, string $entityId, string $phpVersion): CheckResultSet
    {
        $results = new CheckResultSet();

        if ($this->skipWithKnownProblem($results, EntityType::GLOBAL_CONSTANT->value, $entityId, CheckType::CONSTANT_VALUE, $phpVersion)) {
            return $results;
        }

        $existsIn = fn (StubDataQueryInterface $reflection): bool => $this->entityLookup->findConstantById($reflection, $entityId) !== null;
        if (!$this->lastReflectedVersion->isLastVersionWith($phpVersion, $existsIn)) {
            $results->addSuccess($entityId);
            return $results;
        }

        $reflection = $this->reflectionProvider->getReflection($phpVersion);

        $reflConstant = $this->entityLookup->findConstantById($reflection, $entityId);
        if ($reflConstant === null) {
            $results->addSuccess($entityId);
            return $results;
        }

        $stubConstant = $this->entityLookup->findConstantById($stubs, $entityId);
        if ($stubConstant === null) {
            // Not in stubs — ConstantExistsCheck's responsibility
            $results->addSuccess($entityId);
            return $results;
        }

        // Skip resource values — stored as 'PHPSTORM_RESOURCE' by the reflection parser
        if ($reflConstant->getValue() === 'PHPSTORM_RESOURCE') {
            $results->addSuccess($entityId);
            return $results;
        }

        // Skip if either value is null (complex expressions cannot be compared)
        if ($reflConstant->getValue() === null || $stubConstant->getValue() === null) {
            $results->addSuccess($entityId);
            return $results;
        }

        $mismatch = ConstantValueComparator::describeMismatch($reflConstant->getValue(), $stubConstant->getValue());
        if ($mismatch !== null) {
            $results->addFailure($entityId, "Constant {$entityId} value mismatch: {$mismatch}");
            return $results;
        }

        $results->addSuccess($entityId);
        return $results;
    }
}
