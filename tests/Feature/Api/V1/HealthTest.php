<?php

test('health endpoint returns 200 with correct structure', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
            'service' => 'CPAFlow API',
            'version' => 'v1',
        ])
        ->assertJsonStructure(['timestamp']);
});
