<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class OcticonComponentTest extends TestCase
{
    public function test_it_renders_the_requested_octicon(): void
    {
        $markup = Blade::render('<x-octicon name="git-branch" class="me-1" />');

        $this->assertStringContainsString('octicon-git-branch', $markup);
        $this->assertStringContainsString('class="octicon octicon-git-branch me-1"', $markup);
        $this->assertStringContainsString('viewBox="0 0 16 16"', $markup);
        $this->assertStringContainsString('<path d=', $markup);
        $this->assertStringContainsString('aria-hidden="true"', $markup);
    }

    public function test_it_renders_an_accessible_label_when_provided(): void
    {
        $markup = Blade::render('<x-octicon name="repo" label="Repository" />');

        $this->assertStringContainsString('octicon-repo', $markup);
        $this->assertStringContainsString('role="img"', $markup);
        $this->assertStringContainsString('aria-label="Repository"', $markup);
        $this->assertStringNotContainsString('aria-hidden="true"', $markup);
    }
}
