<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function postJsonAsSpa(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withHeader('Origin', $this->spaOrigin())
            ->postJson($uri, $data, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function getJsonAsSpa(string $uri, array $headers = []): TestResponse
    {
        return $this->withHeader('Origin', $this->spaOrigin())
            ->getJson($uri, $headers);
    }

    protected function spaOrigin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
