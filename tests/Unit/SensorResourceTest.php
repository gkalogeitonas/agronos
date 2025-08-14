<?php

namespace Tests\Unit;

use App\Http\Resources\SensorResource;
use App\Models\Sensor;
use App\Enums\SensorType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensorResourceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_expected_fields_and_unit()
    {
        $sensor = Sensor::factory()->create([
            'name' => 'Test Sensor',
            'uuid' => 'test-uuid',
            'type' => SensorType::TEMPERATURE->value,
            'lat' => 12.34,
            'lon' => 56.78,
            'last_reading' => 22.5,
            'last_reading_at' => '2025-08-14 12:00:00',
        ]);

        $resource = (new SensorResource($sensor))->toArray(request());

        $this->assertEquals($sensor->id, $resource['id']);
        $this->assertEquals('Test Sensor', $resource['name']);
        $this->assertEquals('test-uuid', $resource['uuid']);
        $this->assertEquals(SensorType::TEMPERATURE->value, $resource['type']);
        $this->assertEquals(12.34, $resource['lat']);
        $this->assertEquals(56.78, $resource['lon']);
        $this->assertEquals(22.5, $resource['last_reading']);
        $this->assertEquals('2025-08-14 12:00:00', $resource['last_reading_at']);
        $this->assertEquals('°C', $resource['unit']);
    }

    /** @test */
    public function it_returns_empty_unit_for_unknown_type()
    {
        $sensor = Sensor::factory()->make(); // use make() instead of create()
        $sensor->type = 'unknown_type';      // set invalid type in memory only

        $resource = (new SensorResource($sensor))->toArray(request());
        $this->assertEquals('', $resource['unit']);
    }
}
