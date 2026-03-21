<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Validator::class)]
#[UsesClass(ValidationException::class)]
final class FileRuleTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function fakeFile(
        string $name = 'photo.jpg',
        string $type = 'image/jpeg',
        int $size = 10240,
        int $error = UPLOAD_ERR_OK,
    ): array {
        return [
            'name' => $name,
            'type' => $type,
            'tmp_name' => '/tmp/fake_upload',
            'error' => $error,
            'size' => $size,
        ];
    }

    // =========================================================================
    // file
    // =========================================================================

    public function test_file_passes_for_valid_upload(): void
    {
        $v = Validator::make(
            ['avatar' => $this->fakeFile()],
            ['avatar' => ['file']],
        );

        self::assertTrue($v->passes());
    }

    public function test_file_fails_when_upload_has_error(): void
    {
        $v = Validator::make(
            ['avatar' => $this->fakeFile(error: UPLOAD_ERR_INI_SIZE)],
            ['avatar' => ['file']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('avatar', $v->errors());
    }

    public function test_file_fails_for_plain_string(): void
    {
        $v = Validator::make(
            ['avatar' => 'not-a-file'],
            ['avatar' => ['file']],
        );

        self::assertTrue($v->fails());
    }

    public function test_file_skips_when_value_is_null(): void
    {
        $v = Validator::make([], ['avatar' => ['file']]);

        self::assertTrue($v->passes());
    }

    public function test_file_combined_with_required(): void
    {
        $v = Validator::make([], ['avatar' => ['required', 'file']]);

        self::assertTrue($v->fails());
    }

    // =========================================================================
    // image
    // =========================================================================

    public function test_image_passes_for_jpeg(): void
    {
        $v = Validator::make(
            ['photo' => $this->fakeFile(type: 'image/jpeg')],
            ['photo' => ['image']],
        );

        self::assertTrue($v->passes());
    }

    public function test_image_passes_for_png(): void
    {
        $v = Validator::make(
            ['photo' => $this->fakeFile(type: 'image/png')],
            ['photo' => ['image']],
        );

        self::assertTrue($v->passes());
    }

    public function test_image_fails_for_pdf(): void
    {
        $v = Validator::make(
            ['photo' => $this->fakeFile(name: 'doc.pdf', type: 'application/pdf')],
            ['photo' => ['image']],
        );

        self::assertTrue($v->fails());
    }

    public function test_image_fails_for_upload_error(): void
    {
        $v = Validator::make(
            ['photo' => $this->fakeFile(error: UPLOAD_ERR_PARTIAL)],
            ['photo' => ['image']],
        );

        self::assertTrue($v->fails());
    }

    public function test_image_skips_when_null(): void
    {
        $v = Validator::make([], ['photo' => ['image']]);

        self::assertTrue($v->passes());
    }

    // =========================================================================
    // mimes
    // =========================================================================

    public function test_mimes_passes_for_allowed_extension(): void
    {
        $v = Validator::make(
            ['doc' => $this->fakeFile(name: 'report.pdf', type: 'application/pdf')],
            ['doc' => ['mimes:pdf,docx']],
        );

        self::assertTrue($v->passes());
    }

    public function test_mimes_fails_for_disallowed_extension(): void
    {
        $v = Validator::make(
            ['doc' => $this->fakeFile(name: 'photo.jpg', type: 'image/jpeg')],
            ['doc' => ['mimes:pdf,docx']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('doc', $v->errors());
    }

    public function test_mimes_passes_when_extension_in_comma_list(): void
    {
        $v = Validator::make(
            ['img' => $this->fakeFile(name: 'photo.png', type: 'image/png')],
            ['img' => ['mimes:jpg,png,gif']],
        );

        self::assertTrue($v->passes());
    }

    public function test_mimes_fails_for_upload_error(): void
    {
        $v = Validator::make(
            ['img' => $this->fakeFile(error: UPLOAD_ERR_NO_FILE)],
            ['img' => ['mimes:jpg,png']],
        );

        self::assertTrue($v->fails());
    }

    public function test_mimes_skips_when_null(): void
    {
        $v = Validator::make([], ['doc' => ['mimes:pdf']]);

        self::assertTrue($v->passes());
    }

    // =========================================================================
    // max_size
    // =========================================================================

    public function test_max_size_passes_when_file_is_within_limit(): void
    {
        // 512 KB file, limit 1024 KB
        $v = Validator::make(
            ['avatar' => $this->fakeFile(size: 512 * 1024)],
            ['avatar' => ['max_size:1024']],
        );

        self::assertTrue($v->passes());
    }

    public function test_max_size_fails_when_file_exceeds_limit(): void
    {
        // 2 MB file, limit 1 MB (1024 KB)
        $v = Validator::make(
            ['avatar' => $this->fakeFile(size: 2 * 1024 * 1024)],
            ['avatar' => ['max_size:1024']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('avatar', $v->errors());
    }

    public function test_max_size_passes_at_exact_limit(): void
    {
        $v = Validator::make(
            ['avatar' => $this->fakeFile(size: 1024 * 1024)],
            ['avatar' => ['max_size:1024']],
        );

        self::assertTrue($v->passes());
    }

    public function test_max_size_skips_when_null(): void
    {
        $v = Validator::make([], ['avatar' => ['max_size:1024']]);

        self::assertTrue($v->passes());
    }

    public function test_max_size_skips_when_upload_is_invalid(): void
    {
        // max_size alone doesn't report file-type errors — use 'file' for that
        $v = Validator::make(
            ['avatar' => 'not-a-file'],
            ['avatar' => ['max_size:1024']],
        );

        self::assertTrue($v->passes());
    }

    // =========================================================================
    // Combined rules
    // =========================================================================

    public function test_required_file_image_combined(): void
    {
        $v = Validator::make(
            ['photo' => $this->fakeFile(name: 'img.png', type: 'image/png', size: 100 * 1024)],
            ['photo' => ['required', 'file', 'image', 'max_size:512']],
        );

        self::assertTrue($v->passes());
    }

    public function test_full_file_validation_fails_on_wrong_type(): void
    {
        $v = Validator::make(
            ['photo' => $this->fakeFile(name: 'doc.pdf', type: 'application/pdf')],
            ['photo' => ['required', 'file', 'image', 'mimes:jpg,png']],
        );

        self::assertTrue($v->fails());
        // Both 'image' and 'mimes' should add errors
        self::assertGreaterThanOrEqual(2, count($v->errors()['photo']));
    }
}
