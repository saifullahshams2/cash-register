<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_endpoint_returns_valid_pwa_manifest(): void
    {
        $response = $this->get('/manifest.json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');

        $data = $response->json();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('short_name', $data);
        $this->assertSame('/', $data['start_url']);
        $this->assertSame('standalone', $data['display']);
        $this->assertNotEmpty($data['icons']);

        $sizes = array_column($data['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
    }

    public function test_manifest_uses_dynamic_settings(): void
    {
        Setting::set('site_title', 'Custom POS Name');
        Setting::set('site_logo', 'http://localhost/storage/branding/logo.png');

        $response = $this->get('/manifest.json');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame('Custom POS Name', $data['name']);
        $this->assertSame('Custom POS Name', $data['short_name']);

        $srcs = array_column($data['icons'], 'src');
        $this->assertContains('http://localhost/storage/branding/logo.png', $srcs);
    }

    public function test_manifest_uses_pwa_specific_settings(): void
    {
        Setting::set('site_title', 'General Site Title');
        Setting::set('pwa_title', 'Custom Installed PWA App');
        Setting::set('pwa_icon', 'http://localhost/storage/branding/pwa-custom.png');

        $response = $this->get('/manifest.json');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame('Custom Installed PWA App', $data['name']);
        $this->assertSame('Custom Installed PWA App', $data['short_name']);

        $srcs = array_column($data['icons'], 'src');
        $this->assertContains('http://localhost/storage/branding/pwa-custom.png', $srcs);

        // Verify SVG mime type is removed
        $types = array_column($data['icons'], 'type');
        $this->assertNotContains('image/svg+xml', $types);

        // Verify custom icon covers both 192x192 and 512x512
        $customIcons = array_filter($data['icons'], fn ($i) => $i['src'] === 'http://localhost/storage/branding/pwa-custom.png');
        $customSizes = array_column($customIcons, 'sizes');
        $this->assertContains('192x192', $customSizes);
        $this->assertContains('512x512', $customSizes);
    }
}
