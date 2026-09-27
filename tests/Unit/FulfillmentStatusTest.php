<?php
namespace Tests\Unit;
use App\Domain\Commerce\Enums\FulfillmentStatus;
use PHPUnit\Framework\TestCase;
class FulfillmentStatusTest extends TestCase {public function test_expected_production_states_exist():void{$this->assertSame('printing',FulfillmentStatus::Printing->value);$this->assertSame('ready_to_ship',FulfillmentStatus::ReadyToShip->value);}}
