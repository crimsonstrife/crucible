<?php

namespace Tests\Feature;

use App\Filament\Resources\Release\Pages\ListReleases;
use App\Filament\Resources\ReleaseResource;
use App\Models\Release;
use Tests\TestCase;

class ReleaseFilamentResourceTest extends TestCase
{
    public function test_resource_targets_release_model(): void
    {
        $this->assertSame(Release::class, ReleaseResource::getModel());
    }

    public function test_resource_disallows_creation(): void
    {
        $this->assertFalse(ReleaseResource::canCreate());
    }

    public function test_resource_registers_list_page(): void
    {
        $pages = ReleaseResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertSame(ListReleases::class, $pages['index']->getPage());
    }

    public function test_list_page_targets_resource(): void
    {
        $this->assertSame(ReleaseResource::class, ListReleases::getResource());
    }
}
