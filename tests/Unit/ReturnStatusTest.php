<?php
namespace Tests\Unit;
use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnStatus;
use PHPUnit\Framework\TestCase;
class ReturnStatusTest extends TestCase {public function test_return_domain_values_are_explicit():void {$this->assertSame('requested',ReturnStatus::Requested->value);$this->assertSame('resellable',ReturnCondition::Resellable->value);}}
