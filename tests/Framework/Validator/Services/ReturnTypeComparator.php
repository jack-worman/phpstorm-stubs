<?php

namespace StubTests\Framework\Validator\Services;

/**
 * Decides whether a stub return type is equivalent to the reflected one.
 *
 * Extracted because the method and function variants of the return-type check had drifted:
 * ClassMethodsReturnTypesCheck carried deliberate allowances that
 * Functions/FunctionReturnTypesCheck did not, and AbstractCallableCheck::findCallable()
 * resolves "Class::method" ids — so the function variant *can* be handed a method, and would
 * then report a mismatch its sibling accepts. Sharing the decision makes that drift
 * impossible rather than merely unlikely.
 *
 * Only the equivalence decision lives here. The two checks word their messages and their
 * success notes differently, and that is left to them.
 */
final class ReturnTypeComparator
{
    /**
     * Are the two already-normalised types equivalent, allowing for the documented case?
     *
     * A stub `static` is not equivalent to a class name: methods such as Phar::compress() and
     * DateTime::__set_state() return an instance of the base class even when called on a subclass.
     *
     * @param string|null $normalizedRefl Normalised reflection type
     * @param string|null $normalizedStub Normalised stub type
     */
    public static function areEquivalent(?string $normalizedRefl, ?string $normalizedStub): bool
    {
        if ($normalizedRefl === $normalizedStub) {
            return true;
        }

        return self::stubNarrowsBool($normalizedRefl, $normalizedStub);
    }

    /**
     * A stub may narrow `bool` to `true` or `false` (the TentativeType pattern); reflection
     * always reports the wider `bool`. The stub is intentionally more specific.
     */
    private static function stubNarrowsBool(?string $normalizedRefl, ?string $normalizedStub): bool
    {
        return $normalizedRefl === 'bool' && ($normalizedStub === 'true' || $normalizedStub === 'false');
    }
}
