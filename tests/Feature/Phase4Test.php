<?php

namespace Tests\Feature;

use App\Services\BinaryMetadataService;
use App\Support\DiffParser;
use App\Support\MimeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    // ── MimeDetector ──────────────────────────────────────────────────

    public function test_mime_detector_identifies_browser_images(): void
    {
        $this->assertTrue(MimeDetector::isBrowserImage('texture.png'));
        $this->assertTrue(MimeDetector::isBrowserImage('icon.jpg'));
        $this->assertTrue(MimeDetector::isBrowserImage('banner.gif'));
        $this->assertTrue(MimeDetector::isBrowserImage('photo.webp'));
        $this->assertFalse(MimeDetector::isBrowserImage('model.fbx'));
        $this->assertFalse(MimeDetector::isBrowserImage('script.php'));
    }

    public function test_mime_detector_identifies_audio(): void
    {
        $this->assertTrue(MimeDetector::isAudio('sound.mp3'));
        $this->assertTrue(MimeDetector::isAudio('effect.wav'));
        $this->assertTrue(MimeDetector::isAudio('music.ogg'));
        $this->assertFalse(MimeDetector::isAudio('video.mp4'));
    }

    public function test_mime_detector_identifies_video(): void
    {
        $this->assertTrue(MimeDetector::isVideo('intro.mp4'));
        $this->assertTrue(MimeDetector::isVideo('trailer.webm'));
        $this->assertFalse(MimeDetector::isVideo('music.mp3'));
    }

    public function test_mime_detector_identifies_engine_assets(): void
    {
        $this->assertTrue(MimeDetector::isEngineAsset('Content/MyBlueprint.uasset'));
        $this->assertTrue(MimeDetector::isEngineAsset('Maps/Level01.umap'));
        $this->assertTrue(MimeDetector::isEngineAsset('Scenes/Main.unity'));
        $this->assertTrue(MimeDetector::isEngineAsset('scenes/world.tscn'));
        $this->assertFalse(MimeDetector::isEngineAsset('src/main.cpp'));
    }

    public function test_mime_detector_diff_category(): void
    {
        $this->assertEquals('image', MimeDetector::diffCategory('logo.png'));
        $this->assertEquals('audio', MimeDetector::diffCategory('bgm.mp3'));
        $this->assertEquals('video', MimeDetector::diffCategory('cutscene.mp4'));
        $this->assertEquals('model3d', MimeDetector::diffCategory('character.glb'));
        $this->assertEquals('engine_asset', MimeDetector::diffCategory('Blueprint.uasset'));
        $this->assertNull(MimeDetector::diffCategory('readme.md'));
        $this->assertNull(MimeDetector::diffCategory('main.cpp'));
    }

    public function test_mime_from_extension(): void
    {
        $this->assertEquals('image/png', MimeDetector::mimeFromExtension('icon.png'));
        $this->assertEquals('audio/mpeg', MimeDetector::mimeFromExtension('song.mp3'));
        $this->assertEquals('video/mp4', MimeDetector::mimeFromExtension('movie.mp4'));
        $this->assertEquals('model/gltf-binary', MimeDetector::mimeFromExtension('model.glb'));
        $this->assertEquals('application/x-unreal-asset', MimeDetector::mimeFromExtension('BP.uasset'));
        $this->assertEquals('application/octet-stream', MimeDetector::mimeFromExtension('data.xyz'));
    }

    // ── DiffParser — diff_category enrichment ─────────────────────────

    public function test_diff_parser_adds_diff_category_to_files(): void
    {
        $rawDiff = <<<'DIFF'
diff --git a/textures/hero.png b/textures/hero.png
index abc1234..def5678 100644
Binary files a/textures/hero.png and b/textures/hero.png differ
diff --git a/audio/bgm.mp3 b/audio/bgm.mp3
new file mode 100644
index 0000000..abc1234
Binary files /dev/null and b/audio/bgm.mp3 differ
diff --git a/src/main.cpp b/src/main.cpp
index abc1234..def5678 100644
--- a/src/main.cpp
+++ b/src/main.cpp
@@ -1,3 +1,4 @@
 #include <iostream>
+#include <string>
 int main() {
     return 0;
DIFF;

        $files = DiffParser::parse($rawDiff);

        $this->assertCount(3, $files);

        // Image file
        $this->assertEquals('textures/hero.png', $files[0]['file_name']);
        $this->assertEquals('image', $files[0]['diff_category']);
        $this->assertEquals('image/png', $files[0]['mime_type']);
        $this->assertTrue($files[0]['is_binary']);

        // Audio file
        $this->assertEquals('audio/bgm.mp3', $files[1]['file_name']);
        $this->assertEquals('audio', $files[1]['diff_category']);
        $this->assertTrue($files[1]['is_new']);

        // Code file — no diff category
        $this->assertEquals('src/main.cpp', $files[2]['file_name']);
        $this->assertNull($files[2]['diff_category']);
        $this->assertFalse($files[2]['is_binary']);
    }

    // ── BinaryMetadataService ─────────────────────────────────────────

    public function test_image_metadata_extraction(): void
    {
        $service = new BinaryMetadataService;

        // Create a minimal valid PNG (1x1 pixel)
        $png = $this->createMinimalPng();

        $meta = $service->extract('test.png', $png);

        $this->assertNotNull($meta);
        $this->assertEquals('image', $meta['type']);
        $this->assertEquals('png', $meta['extension']);
        $this->assertEquals(1, $meta['width']);
        $this->assertEquals(1, $meta['height']);
        $this->assertEquals('PNG', $meta['format']);
        $this->assertArrayHasKey('size', $meta);
    }

    public function test_wav_metadata_extraction(): void
    {
        $service = new BinaryMetadataService;

        // Create a minimal valid WAV header
        $wav = $this->createMinimalWav();

        $meta = $service->extract('effect.wav', $wav);

        $this->assertNotNull($meta);
        $this->assertEquals('audio', $meta['type']);
        $this->assertEquals('wav', $meta['extension']);
        $this->assertEquals(44100, $meta['sample_rate']);
        $this->assertEquals(2, $meta['channels']);
        $this->assertEquals(16, $meta['bits_per_sample']);
        $this->assertEquals('PCM', $meta['format']);
    }

    public function test_uasset_metadata_extraction(): void
    {
        $service = new BinaryMetadataService;

        // Create a minimal .uasset header with valid magic
        $uasset = pack('V', 0x9E2A83C1)       // Magic
            . pack('l', -7)                     // Legacy version
            . pack('V', 0)                      // padding
            . pack('l', 522)                    // UE4 file version
            . str_repeat("\0", 100);             // Padding

        $meta = $service->extract('Content/BP_Hero.uasset', $uasset);

        $this->assertNotNull($meta);
        $this->assertEquals('engine_asset', $meta['type']);
        $this->assertEquals('Unreal Engine', $meta['engine']);
        $this->assertTrue($meta['valid_header']);
        $this->assertEquals('Asset', $meta['asset_type']);
    }

    public function test_umap_metadata_extraction(): void
    {
        $service = new BinaryMetadataService;

        $umap = pack('V', 0x9E2A83C1) . str_repeat("\0", 100);

        $meta = $service->extract('Maps/Level01.umap', $umap);

        $this->assertEquals('Level/Map', $meta['asset_type']);
        $this->assertEquals('Unreal Engine', $meta['engine']);
    }

    public function test_obj_metadata_extraction(): void
    {
        $service = new BinaryMetadataService;

        $obj = "# OBJ file\nv 0 0 0\nv 1 0 0\nv 0 1 0\nf 1 2 3\n";

        $meta = $service->extract('model.obj', $obj);

        $this->assertNotNull($meta);
        $this->assertEquals('model3d', $meta['type']);
        $this->assertEquals('Wavefront OBJ', $meta['format']);
        $this->assertEquals(3, $meta['vertices']);
        $this->assertEquals(1, $meta['faces']);
    }

    public function test_glb_metadata_extraction(): void
    {
        $service = new BinaryMetadataService;

        // GLB magic + version 2 + length
        $glb = pack('V', 0x46546C67) . pack('V', 2) . pack('V', 1024) . str_repeat("\0", 50);

        $meta = $service->extract('scene.glb', $glb);

        $this->assertNotNull($meta);
        $this->assertEquals('model3d', $meta['type']);
        $this->assertEquals('glTF Binary', $meta['format']);
        $this->assertEquals(2, $meta['version']);
    }

    public function test_generic_binary_metadata(): void
    {
        $service = new BinaryMetadataService;

        $meta = $service->extract('data.bin', 'some binary contents');

        $this->assertNotNull($meta);
        $this->assertEquals('binary', $meta['type']);
        $this->assertArrayHasKey('size', $meta);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Create a minimal valid 1x1 PNG.
     */
    private function createMinimalPng(): string
    {
        $im = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    /**
     * Create a minimal valid WAV file header.
     */
    private function createMinimalWav(): string
    {
        $channels = 2;
        $sampleRate = 44100;
        $bitsPerSample = 16;
        $dataSize = 44100 * 2 * 2; // 1 second of audio
        $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
        $blockAlign = $channels * ($bitsPerSample / 8);

        return 'RIFF'
            . pack('V', 36 + $dataSize)  // ChunkSize
            . 'WAVE'
            . 'fmt '
            . pack('V', 16)             // Subchunk1Size
            . pack('v', 1)              // AudioFormat (PCM)
            . pack('v', $channels)
            . pack('V', $sampleRate)
            . pack('V', $byteRate)
            . pack('v', $blockAlign)
            . pack('v', $bitsPerSample)
            . 'data'
            . pack('V', $dataSize)
            . str_repeat("\0", 100);     // Some dummy audio data
    }
}
