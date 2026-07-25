<?php

namespace ElfSundae\Laravel\Hashid;

use Brick\Math\BigInteger;

/**
 * Encodes a UUID as a short, URL-safe id, and decodes it back.
 *
 * Hashids cannot do this. Its encode path calls intval, and a UUID read as an
 * integer is far beyond PHP_INT_MAX, so applications wanting a tidy public id
 * for a UUID primary key ended up writing their own arbitrary-precision
 * encoder. Appbase did exactly that in
 * HasAppBaseAttributes::encodeUuidForFrontend, and shipped no inverse, which
 * left every consumer holding ids that could be issued but never resolved.
 *
 * This is that algorithm, with its inverse, living where an encoding belongs.
 * The output is byte-identical to Appbase's, so ids already issued to clients
 * keep working.
 *
 * The UUID is read as a single 128-bit integer and rewritten in the configured
 * alphabet, left padded to a minimum length with the alphabet's zero digit.
 * That padding falls out of the arithmetic on the way back, so it needs no
 * special handling when decoding.
 */
class UuidDriver implements DriverInterface
{
    /**
     * @var string
     */
    protected $alphabet;

    /**
     * @var int
     */
    protected $minLength;

    public function __construct(array $config = [])
    {
        $this->alphabet = (string) ($config['alphabet'] ?? '');
        $this->minLength = (int) ($config['min_length'] ?? 0);
    }

    /**
     * Encode a UUID. Anything that is not one returns an empty string, so a
     * caller can fall through to another driver rather than be thrown at.
     *
     * @param  mixed  $data
     * @return string
     */
    public function encode($data)
    {
        $hex = strtolower(str_replace('-', '', (string) $data));

        if (! preg_match('/^[0-9a-f]{32}$/', $hex)) {
            return '';
        }

        if (! $base = $this->base()) {
            return '';
        }

        $number = BigInteger::fromBase($hex, 16);
        $encoded = '';

        if ($number->isZero()) {
            $encoded = $this->alphabet[0];
        } else {
            while (! $number->isZero()) {
                [$number, $remainder] = $number->quotientAndRemainder($base);
                $encoded = $this->alphabet[$remainder->toInt()].$encoded;
            }
        }

        return $this->minLength > 0
            ? str_pad($encoded, $this->minLength, $this->alphabet[0], STR_PAD_LEFT)
            : $encoded;
    }

    /**
     * Decode an id back to its UUID, or null if it is not one of ours.
     *
     * @param  mixed  $data
     * @return string|null
     */
    public function decode($data)
    {
        $data = (string) $data;

        if ($data === '' || ! $base = $this->base()) {
            return null;
        }

        $number = BigInteger::zero();

        foreach (str_split($data) as $char) {
            $digit = strpos($this->alphabet, $char);

            if ($digit === false) {
                return null;
            }

            $number = $number->multipliedBy($base)->plus($digit);
        }

        $hex = $number->toBase(16);

        // More than 128 bits was never one of our ids.
        if (strlen($hex) > 32) {
            return null;
        }

        $hex = str_pad($hex, 32, '0', STR_PAD_LEFT);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * @return \Brick\Math\BigInteger|null
     */
    protected function base()
    {
        $unique = strlen(count_chars($this->alphabet, 3));

        return $unique < 2 ? null : BigInteger::of($unique);
    }
}
