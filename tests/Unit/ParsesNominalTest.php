<?php

namespace Tests\Unit;

use App\Traits\ParsesNominal;
use PHPUnit\Framework\TestCase;

class ParsesNominalTest extends TestCase
{
    private object $consumer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consumer = new class
        {
            use ParsesNominal {
                parseNominal as public;
            }
        };
    }

    public function test_parses_plain_integer_string(): void
    {
        $this->assertSame(50000.0, $this->consumer->parseNominal('50000'));
        $this->assertSame(1000000.0, $this->consumer->parseNominal('1000000'));
    }

    public function test_parses_indonesian_thousands_format(): void
    {
        $this->assertSame(50000.0, $this->consumer->parseNominal('50.000'));
        $this->assertSame(1000000.0, $this->consumer->parseNominal('1.000.000'));
        $this->assertSame(25000000.0, $this->consumer->parseNominal('25.000.000'));
    }

    public function test_parses_transitional_typing_dot(): void
    {
        // Saat mengetik digit ke-5 pada "5.000" -> "5.0000", harus tetap bernilai 50000 bukan 5.0
        $this->assertSame(50000.0, $this->consumer->parseNominal('5.0000'));
        $this->assertSame(500000.0, $this->consumer->parseNominal('50.0000'));
    }

    public function test_parses_decimal_with_comma(): void
    {
        $this->assertSame(50000.5, $this->consumer->parseNominal('50.000,50'));
        $this->assertSame(1250.75, $this->consumer->parseNominal('1.250,75'));
        $this->assertSame(5.5, $this->consumer->parseNominal('5,5'));
    }

    public function test_parses_decimal_with_dot_standard(): void
    {
        $this->assertSame(1250.5, $this->consumer->parseNominal('1250.50'));
        $this->assertSame(50000.0, $this->consumer->parseNominal('50000.00'));
    }

    public function test_handles_rp_prefix_and_spaces(): void
    {
        $this->assertSame(50000.0, $this->consumer->parseNominal('Rp 50.000'));
        $this->assertSame(50000.0, $this->consumer->parseNominal('Rp. 50.000 '));
    }

    public function test_handles_numeric_primitives(): void
    {
        $this->assertSame(50000.0, $this->consumer->parseNominal(50000));
        $this->assertSame(50000.5, $this->consumer->parseNominal(50000.5));
    }

    public function test_handles_empty_and_null(): void
    {
        $this->assertSame(0.0, $this->consumer->parseNominal(''));
        $this->assertSame(0.0, $this->consumer->parseNominal(null));
        $this->assertSame(0.0, $this->consumer->parseNominal('   '));
    }
}
