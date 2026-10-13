<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Form;

use Nowo\DoctrineEncryptBundle\Form\SecretFieldApplier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretFieldApplierTest extends TestCase
{
    /**
     * @return list<string|null>
     */
    private function collect(callable $apply): array
    {
        $calls = [];
        $apply(static function (?string $value) use (&$calls): void {
            $calls[] = $value;
        });

        return $calls;
    }

    public function testClearSetsNullEvenWithPlainValue(): void
    {
        self::assertSame([null], $this->collect(static fn (callable $s) => SecretFieldApplier::apply(true, 'new-secret', $s)));
        self::assertSame([null], $this->collect(static fn (callable $s) => SecretFieldApplier::apply(true, null, $s)));
    }

    public function testNonEmptyPlainSetsValue(): void
    {
        self::assertSame(['new-secret'], $this->collect(static fn (callable $s) => SecretFieldApplier::apply(false, 'new-secret', $s)));
    }

    public function testBlankOrNullPlainKeepsStoredValue(): void
    {
        self::assertSame([], $this->collect(static fn (callable $s) => SecretFieldApplier::apply(false, null, $s)));
        self::assertSame([], $this->collect(static fn (callable $s) => SecretFieldApplier::apply(false, '', $s)));
    }

    /**
     * @return iterable<string, array{mixed, list<string|null>}>
     */
    public static function dataProvider(): iterable
    {
        yield 'null payload keeps' => [null, []];
        yield 'non-array payload keeps' => ['secret', []];
        yield 'empty array keeps' => [[], []];
        yield 'blank value keeps' => [['value' => '', 'clear' => false], []];
        yield 'null value keeps' => [['value' => null, 'clear' => false], []];
        yield 'non-string value keeps' => [['value' => 123], []];
        yield 'value sets' => [['value' => 're_abc'], ['re_abc']];
        yield 'value without clear key sets' => [['value' => 're_abc', 'clear' => false], ['re_abc']];
        yield 'clear wins' => [['value' => 're_abc', 'clear' => true], [null]];
        yield 'clear only' => [['clear' => true], [null]];
        yield 'truthy non-bool clear ignored' => [['clear' => '1'], []];
    }

    /**
     * @param list<string|null> $expected
     */
    #[DataProvider('dataProvider')]
    public function testApplyData(mixed $data, array $expected): void
    {
        self::assertSame($expected, $this->collect(static fn (callable $s) => SecretFieldApplier::applyData($data, $s)));
    }
}
