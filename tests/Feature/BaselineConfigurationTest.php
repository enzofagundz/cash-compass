<?php

namespace Tests\Feature;

use Tests\TestCase;

class BaselineConfigurationTest extends TestCase
{
    public function test_application_uses_sao_paulo_timezone_and_brazilian_locale(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('pt_BR', config('app.locale'));
        $this->assertSame('pt_BR', config('app.faker_locale'));
    }
}
