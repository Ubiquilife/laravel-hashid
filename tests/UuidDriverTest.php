<?php

namespace ElfSundae\Laravel\Hashid\Test;

use Brick\Math\BigInteger;
use ElfSundae\Laravel\Hashid\UuidDriver;
use PHPUnit\Framework\TestCase;

/**
 * The driver has one job Hashids cannot do: encode a UUID.
 *
 * Hashids' encode path calls intval, and a UUID read as an integer is far
 * beyond PHP_INT_MAX, so Appbase grew its own arbitrary-precision encoder
 * (HasAppBaseAttributes::encodeUuidForFrontend) and shipped no inverse. Every
 * public id on the platform comes from that encoder, so this driver has to
 * produce byte-identical output or every existing id breaks.
 *
 * The compatibility test below is therefore the important one: it does not
 * assert a value someone typed out, it asserts agreement with a reference
 * implementation of the algorithm Appbase actually runs.
 */
class UuidDriverTest extends TestCase
{
    /** The platform's configured alphabet and minimum length. */
    private const ALPHABET = '23456789ABCDEFGHJKMPQRSTVWXYZ';

    private const MIN_LENGTH = 27;

    private function driver(): UuidDriver
    {
        return new UuidDriver([
            'alphabet' => self::ALPHABET,
            'min_length' => self::MIN_LENGTH,
        ]);
    }

    /**
     * Appbase's encodeUuidForFrontend, reproduced exactly as the reference to
     * agree with. If the driver ever drifts from this, existing ids stop
     * resolving.
     */
    private function appbaseEncode(string $value): string
    {
        $hex = strtolower(str_replace('-', '', $value));
        if (! preg_match('/^[0-9a-f]{32}$/', $hex)) {
            return '';
        }

        $alphabet = self::ALPHABET;
        $alphabetLength = strlen(count_chars($alphabet, 3));
        if ($alphabetLength < 2) {
            return '';
        }

        $base = BigInteger::of($alphabetLength);
        $number = BigInteger::fromBase($hex, 16);
        $encoded = '';

        if ($number->isZero()) {
            $encoded = $alphabet[0];
        } else {
            while (! $number->isZero()) {
                [$number, $remainder] = $number->quotientAndRemainder($base);
                $encoded = $alphabet[$remainder->toInt()].$encoded;
            }
        }

        if (self::MIN_LENGTH > 0) {
            $encoded = str_pad($encoded, self::MIN_LENGTH, $alphabet[0], STR_PAD_LEFT);
        }

        return $encoded;
    }

    public static function uuidProvider(): array
    {
        return [
            'a real conversion key' => ['5d929ebe-d9aa-470f-a5f9-568e3d76a5ce'],
            'all zeroes' => ['00000000-0000-0000-0000-000000000000'],
            'all ffs' => ['ffffffff-ffff-ffff-ffff-ffffffffffff'],
            'leading zeroes' => ['00000001-0000-4000-8000-000000000001'],
            'uppercase input' => ['5D929EBE-D9AA-470F-A5F9-568E3D76A5CE'],
        ];
    }

    /**
     * @dataProvider uuidProvider
     */
    public function testItEncodesExactlyAsAppbaseDoes(string $uuid)
    {
        $this->assertSame(
            $this->appbaseEncode($uuid),
            $this->driver()->encode($uuid),
            'the driver must produce the ids already issued to clients'
        );
    }

    /**
     * @dataProvider uuidProvider
     */
    public function testItDecodesBackToTheUuid(string $uuid)
    {
        $driver = $this->driver();

        $this->assertSame(
            strtolower($uuid),
            $driver->decode($driver->encode($uuid)),
            'this inverse is what Appbase never shipped'
        );
    }

    public function testEncodedIdsAreAtLeastTheMinimumLength()
    {
        $this->assertSame(
            self::MIN_LENGTH,
            strlen($this->driver()->encode('00000000-0000-0000-0000-000000000001'))
        );
    }

    public function testItRefusesAnythingThatIsNotAUuid()
    {
        $driver = $this->driver();

        $this->assertSame('', $driver->encode('not-a-uuid'));
        $this->assertSame('', $driver->encode('12345'));
        $this->assertSame('', $driver->encode(''));
    }

    public function testDecodingSomethingOutsideTheAlphabetReturnsNull()
    {
        $this->assertNull($this->driver()->decode('lowercase-and-punctuation!'));
    }

    public function testDecodingAnEmptyStringReturnsNull()
    {
        $this->assertNull($this->driver()->decode(''));
    }

    public function testDecodingAValueTooLargeForAUuidReturnsNull()
    {
        // 40 characters of the alphabet's highest digit overflows 128 bits.
        $this->assertNull($this->driver()->decode(str_repeat('Z', 40)));
    }
}
