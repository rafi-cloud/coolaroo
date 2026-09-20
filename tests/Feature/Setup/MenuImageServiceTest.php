<?php

namespace Tests\Feature\Setup;

use App\Services\MenuImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MenuImageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_storage_link_exists(): void
    {
        $this->assertFileExists(public_path('storage'));
    }

    public function test_it_stores_an_uploaded_image_under_menu(): void
    {
        $file = UploadedFile::fake()->image('pizza.jpg', 1600, 1200);

        $path = (new MenuImageService)->store($file);

        $this->assertStringStartsWith('menu/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_large_image_is_scaled_down_to_the_max_width(): void
    {
        $file = UploadedFile::fake()->image('big.jpg', 3000, 2000);

        $path = (new MenuImageService)->store($file);

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(1200, $width);
    }

    public function test_a_small_image_is_not_upscaled(): void
    {
        $file = UploadedFile::fake()->image('small.jpg', 300, 200);

        $path = (new MenuImageService)->store($file);

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(300, $width);
    }

    public function test_delete_removes_the_stored_file(): void
    {
        $file = UploadedFile::fake()->image('to-delete.jpg', 500, 500);
        $service = new MenuImageService;
        $path = $service->store($file);

        Storage::disk('public')->assertExists($path);

        $service->delete($path);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_with_a_null_path_does_nothing(): void
    {
        (new MenuImageService)->delete(null);

        $this->addToAssertionCount(1);
    }

    public function test_validation_rules_reject_a_non_image_file(): void
    {
        $file = UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf');

        $validator = Validator::make(['image' => $file], ['image' => MenuImageService::rules()]);

        $this->assertTrue($validator->fails());
    }

    public function test_validation_rules_reject_an_oversized_file(): void
    {
        $file = UploadedFile::fake()->image('huge.jpg')->size(5000);

        $validator = Validator::make(['image' => $file], ['image' => MenuImageService::rules()]);

        $this->assertTrue($validator->fails());
    }

    public function test_validation_rules_accept_a_normal_image(): void
    {
        $file = UploadedFile::fake()->image('ok.jpg', 800, 800)->size(500);

        $validator = Validator::make(['image' => $file], ['image' => MenuImageService::rules()]);

        $this->assertFalse($validator->fails());
    }
}
