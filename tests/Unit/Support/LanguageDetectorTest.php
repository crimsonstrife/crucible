<?php

namespace Tests\Unit\Support;

use App\Support\LanguageDetector;
use Tests\TestCase;

class LanguageDetectorTest extends TestCase
{
    public function test_it_detects_common_languages_by_extension(): void
    {
        $this->assertSame('PHP', LanguageDetector::forPath('src/Http/Controller.php'));
        $this->assertSame('JavaScript', LanguageDetector::forPath('resources/js/app.js'));
        $this->assertSame('TypeScript', LanguageDetector::forPath('resources/js/app.ts'));
        $this->assertSame('Python', LanguageDetector::forPath('tools/script.py'));
        $this->assertSame('Go', LanguageDetector::forPath('cmd/main.go'));
        $this->assertSame('Rust', LanguageDetector::forPath('src/lib.rs'));
        $this->assertSame('C++', LanguageDetector::forPath('engine/core.cpp'));
        $this->assertSame('C#', LanguageDetector::forPath('Assets/Game.cs'));
        $this->assertSame('Shell', LanguageDetector::forPath('bin/build.sh'));
        $this->assertSame('HTML', LanguageDetector::forPath('public/index.html'));
    }

    public function test_it_detects_blade_templates_via_compound_extension(): void
    {
        $this->assertSame('Blade', LanguageDetector::forPath('resources/views/layouts/app.blade.php'));
    }

    public function test_it_detects_languages_by_special_filename(): void
    {
        $this->assertSame('Dockerfile', LanguageDetector::forPath('Dockerfile'));
        $this->assertSame('Dockerfile', LanguageDetector::forPath('deploy/Dockerfile'));
        $this->assertSame('Makefile', LanguageDetector::forPath('Makefile'));
        $this->assertSame('CMake', LanguageDetector::forPath('CMakeLists.txt'));
    }

    public function test_it_returns_null_for_unknown_extensions(): void
    {
        $this->assertNull(LanguageDetector::forPath('README'));
        $this->assertNull(LanguageDetector::forPath('LICENSE'));
        $this->assertNull(LanguageDetector::forPath('binary.unknownext'));
        $this->assertNull(LanguageDetector::forPath('noextension'));
    }

    public function test_it_matches_excluded_path_segments(): void
    {
        $this->assertTrue(LanguageDetector::isExcludedPath('node_modules/foo/index.js'));
        $this->assertTrue(LanguageDetector::isExcludedPath('vendor/bar/src/Foo.php'));
        $this->assertTrue(LanguageDetector::isExcludedPath('dist/bundle.js'));
        $this->assertTrue(LanguageDetector::isExcludedPath('apps/web/.next/cache/x'));
        $this->assertTrue(LanguageDetector::isExcludedPath('target/debug/foo'));
        $this->assertTrue(LanguageDetector::isExcludedPath('backend/__pycache__/foo.pyc'));
    }

    public function test_it_does_not_match_similar_paths_that_are_not_exact_segments(): void
    {
        $this->assertFalse(LanguageDetector::isExcludedPath('src/node_modules_docs.md'));
        $this->assertFalse(LanguageDetector::isExcludedPath('vendors/foo.php'));
        $this->assertFalse(LanguageDetector::isExcludedPath('distribution/notes.md'));
        $this->assertFalse(LanguageDetector::isExcludedPath('src/app.ts'));
    }
}
