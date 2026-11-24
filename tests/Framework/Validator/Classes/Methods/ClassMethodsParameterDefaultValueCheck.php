<?php

namespace StubTests\Framework\Validator\Classes\Methods;

use StubTests\Framework\Validator\AbstractMemberFlagCheck;
use StubTests\Framework\Validator\Contracts\DescribesMethodMismatch;
use StubTests\Framework\Validator\Contracts\MemberKind;
use StubTests\Framework\Model\PHPMethod;
use StubTests\Framework\Validator\KnownProblems\CheckType;
use StubTests\Framework\Validator\Services\ParameterDefaultValueComparator;

/**
 * Validates that default parameter values in stub methods match those in reflection.
 *
 * Runs from PHP 8.0, the first version whose reflection reports internal defaults. Below the
 * latest version only declarations that end before it are compared (see
 * ParameterDefaultValueComparator::filterComparableAt()).
 *
 * For each class identified by $entityId the validator:
 * 1. Iterates all methods reported by reflection.
 * 2. For each reflection parameter that has an accessible default value, checks
 *    whether the stub parameter declares a matching evaluated default.
 * 3. Comparison is skipped when either side's value is null — this avoids false
 *    positives from stub constants that cannot be evaluated at parse time
 *    (e.g. extension-specific constants) and from genuine null defaults whose
 *    ambiguity we cannot resolve without a richer model.
 * 4. Parameter absent from stubs → silently skipped (ParametersCountCheck handles it).
 * 5. Stub has no default at all → silently skipped (OptionalParametersCheck handles it).
 *
 * Known problems are supported at two granularities:
 * - class-level:  EntityType::CLASS_TYPE + classId + 'ParameterDefaultValueCheck'
 * - method-level: EntityType::METHOD + '\ClassName::methodName' + 'ParameterDefaultValueCheck'
 */
class ClassMethodsParameterDefaultValueCheck extends AbstractMemberFlagCheck implements DescribesMethodMismatch
{
    protected function memberKind(): MemberKind
    {
        return MemberKind::METHOD;
    }

    public function supports(string $phpVersion): bool
    {
        return version_compare($phpVersion, '8.0', '>=');
    }

    protected function getCheckName(): CheckType
    {
        return CheckType::PARAMETER_DEFAULT_VALUE;
    }

    public function describeMethodMismatch(
        string $methodEntityId,
        PHPMethod $reflMethod,
        PHPMethod $stubMethod,
        string $phpVersion
    ): ?string {
        $stubParamsByName = [];
        foreach ($this->methodCollection->filterAndDeduplicateParams($stubMethod->getParameters(), $phpVersion) as $param) {
            $stubParamsByName[$param->getName()] = $param;
        }

        $stubParamsByName = ParameterDefaultValueComparator::filterComparableAt($stubParamsByName, $stubMethod, $phpVersion);
        $mismatches = ParameterDefaultValueComparator::buildMismatches($reflMethod->getParameters(), $stubParamsByName);

        if (empty($mismatches)) {
            return null;
        }

        return "Method {$methodEntityId}: parameter default value mismatch(es): "
            . implode('; ', $mismatches);
    }
}
