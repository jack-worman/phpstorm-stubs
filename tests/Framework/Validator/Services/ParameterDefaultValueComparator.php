<?php

namespace StubTests\Framework\Validator\Services;

use StubTests\Framework\Model\BasePHPElement;
use StubTests\Framework\Model\PHPParameter;
use StubTests\Framework\Runner\PhpVersions;

/**
 * Compares default parameter values between reflection and stubs.
 */
final class ParameterDefaultValueComparator
{
    /**
     * Keeps the stub parameters whose defaults are compared at $phpVersion.
     *
     * Defaults are compared at the latest version, where every declaration that reaches it is
     * covered. Below it only declarations that end earlier are compared, since they never reach the
     * latest version; comparing the others would report every default PHP changed since.
     *
     * @param  array<string, PHPParameter> $stubParamsByName
     * @return array<string, PHPParameter>
     */
    public static function filterComparableAt(array $stubParamsByName, BasePHPElement $stubCallable, string $phpVersion): array
    {
        if ($phpVersion === PhpVersions::LATEST->value || $stubCallable->getStubsMetadata()?->getRemovedVersion() !== null) {
            return $stubParamsByName;
        }

        return array_filter(
            $stubParamsByName,
            static fn (PHPParameter $param): bool => $param->getStubsMetadata()?->getRemovedVersion() !== null
        );
    }

    /**
     * Compare default values for each reflection parameter against the stub param map.
     *
     * Skips parameters that:
     * - have no default in reflection
     * - are absent from the stub param map (ParametersCountCheck's responsibility)
     * - have no default in the stub (OptionalParametersCheck's responsibility)
     * - have a default on either side that could not be evaluated (an unresolvable constant,
     *   say), whose null value says nothing about the real default
     *
     * @param  iterable                     $reflParams       reflection parameters
     * @param  array<string, PHPParameter>  $stubParamsByName stub parameters indexed by name
     * @return string[]
     */
    public static function buildMismatches(iterable $reflParams, array $stubParamsByName): array
    {
        $mismatches = [];

        foreach ($reflParams as $reflParam) {
            $name = $reflParam->getName();

            if (!$reflParam->hasDefaultValue()) {
                continue;
            }

            if (!isset($stubParamsByName[$name])) {
                continue;
            }

            $stubParam = $stubParamsByName[$name];

            if (!$stubParam->hasDefaultValue()) {
                continue;
            }

            if (!$reflParam->isDefaultValueResolved() || !$stubParam->isDefaultValueResolved()) {
                continue;
            }

            $reflValue = $reflParam->getDefaultValue();
            $stubValue = $stubParam->getDefaultValue();

            if ($reflValue !== $stubValue) {
                $mismatches[] = sprintf(
                    '$%s: reflection \'%s\', stubs \'%s\'',
                    $name,
                    self::formatValue($reflValue),
                    self::formatValue($stubValue)
                );
            }
        }

        return $mismatches;
    }

    public static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_string($value)) {
            return "'{$value}'";
        }
        if (is_array($value)) {
            return '[]';
        }
        return (string)$value;
    }
}
