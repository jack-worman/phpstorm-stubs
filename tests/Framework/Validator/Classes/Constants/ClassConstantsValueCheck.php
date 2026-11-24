<?php

namespace StubTests\Framework\Validator\Classes\Constants;

use StubTests\Framework\Model\PHPClassConstant;
use StubTests\Framework\Storage\StubDataQueryInterface;
use StubTests\Framework\Validator\AbstractConstantFlagCheck;
use StubTests\Framework\Validator\KnownProblems\CheckType;
use StubTests\Framework\Validator\Services\ConstantValueComparator;
use StubTests\Framework\Validator\Services\LastReflectedVersionService;

/**
 * Validates that the values of constants in class stubs match reflection.
 *
 * Value comparison is limited to the last PHP version that has the constant: the latest one, or
 * the one before PHP removed it. Earlier versions would only report historical value changes.
 *
 * For each class identified by $entityId the validator:
 * 1. Iterates all version-available constants declared directly in the stub.
 * 2. Looks up each constant by name in the reflection constant map (which includes all
 *    inherited constants).
 * 3. If the constant is not found in reflection it is silently skipped — existence is
 *    ClassConstantsCheck's responsibility.
 * 4. When both sides are found and no later PHP version has the constant, their values are compared
 *    and any mismatch, of value or of type, is reported as a failure. Either side having a
 *    null value skips
 *    the check (complex/dynamic expressions cannot be compared).
 *
 * Known problems are supported at two granularities:
 * - entity-level: EntityType::CLASS_TYPE + classId + 'ClassConstantsValueCheck'
 *   → skips all value checks for the class.
 * - constant-level: EntityType::CLASS_CONSTANT + '\ClassName::CONST_NAME' + 'ClassConstantsValueCheck'
 *   → skips only that specific constant.
 */
class ClassConstantsValueCheck extends AbstractConstantFlagCheck
{
    protected function getCheckName(): CheckType
    {
        return CheckType::CLASS_CONSTANTS_VALUE;
    }

    protected function describeMismatch(
        string $constantEntityId,
        PHPClassConstant $reflConstant,
        PHPClassConstant $stubConstant,
        string $phpVersion
    ): ?string {
        if ($reflConstant->getValue() === null || $stubConstant->getValue() === null) {
            return null;
        }

        [$classId, $name] = explode('::', $constantEntityId, 2);
        $existsIn = function (StubDataQueryInterface $reflection) use ($classId, $name): bool {
            foreach ($this->lookupEntityById($reflection, $classId)?->getConstants() ?? [] as $constant) {
                if ($constant->getName() === $name) {
                    return true;
                }
            }
            return false;
        };
        if (!(new LastReflectedVersionService($this->reflectionProvider))->isLastVersionWith($phpVersion, $existsIn)) {
            return null;
        }

        $mismatch = ConstantValueComparator::describeMismatch($reflConstant->getValue(), $stubConstant->getValue());

        return $mismatch === null ? null : "Constant {$constantEntityId} value mismatch: {$mismatch}";
    }
}
