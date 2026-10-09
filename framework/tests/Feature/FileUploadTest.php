<?php

namespace Simple\Tests\Feature;

use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Http\UploadedFile;
use Simple\Storage\Storage;
use Simple\Tests\TestCase;

class FileUploadTest extends TestCase
{
    protected bool $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->router->post('/avatar', function (Request $request) {
            $request->validate(['avatar' => 'required|image|max:1024']);
            $path = $request->file('avatar')->store('avatars', 'public');

            return Response::created(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
        });

        $this->app->router->post('/documents', function (Request $request) {
            $request->validate(['document' => 'required|file|mimes:pdf,csv']);
            return Response::created(['path' => $request->file('document')->store('documents')]);
        });

        $this->app->router->post('/gallery', function (Request $request) {
            $paths = array_map(fn (UploadedFile $f) => $f->store('gallery', 'public'), $request->file('photos'));
            return Response::created($paths);
        });
    }

    public function test_image_upload_is_stored_on_the_public_disk(): void
    {
        $disk = Storage::fake('public');

        $response = $this->postJson('/avatar', ['avatar' => UploadedFile::fakeImage('me.png')])->assertCreated();

        $path = $response->json('data.path');
        $this->assertTrue($disk->exists($path));
        $this->assertSame('http://simple.test/uploads/' . $path, $response->json('data.url'));
    }

    public function test_missing_file_fails_validation(): void
    {
        $this->postJson('/avatar', [])->assertJsonValidationErrors(['avatar']);
    }

    public function test_non_image_is_rejected_even_with_an_image_name(): void
    {
        Storage::fake('public');

        $this->postJson('/avatar', ['avatar' => UploadedFile::fake('cat.png', '<?php echo "pwned";', 'image/png')])
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_svg_is_not_accepted_as_an_image(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->postJson('/avatar', ['avatar' => UploadedFile::fake('logo.svg', $svg, 'image/svg+xml')])
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_max_size_in_kilobytes(): void
    {
        $big = UploadedFile::fake('big.png', UploadedFile::fakeImage()->contents() . str_repeat("\0", 1100 * 1024));

        $response = $this->postJson('/avatar', ['avatar' => $big])->assertJsonValidationErrors(['avatar']);
        $this->assertStringContainsString('1024 kilobytes', $response->json('errors.avatar.0'));
    }

    public function test_mimes_rule_checks_real_content(): void
    {
        Storage::fake('local');

        $pdf = UploadedFile::fake('report.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->postJson('/documents', ['document' => $pdf])->assertCreated();

        $csv = UploadedFile::fake('data.csv', "name,age\nada,36\n");
        $this->postJson('/documents', ['document' => $csv])->assertCreated();

        $fakePdf = UploadedFile::fake('report.pdf', 'just text pretending to be a pdf');
        $this->postJson('/documents', ['document' => $fakePdf])->assertJsonValidationErrors(['document']);
    }

    public function test_private_files_are_stored_outside_public(): void
    {
        $disk = Storage::fake('local');
        $pdf = UploadedFile::fake('report.pdf', "%PDF-1.4\n%%EOF");

        $path = $this->postJson('/documents', ['document' => $pdf])->assertCreated()->json('data.path');

        $this->assertTrue($disk->exists($path));
        $this->assertStringEndsWith('.pdf', $path);
    }

    public function test_multiple_files(): void
    {
        $disk = Storage::fake('public');

        $paths = $this->postJson('/gallery', ['photos' => [UploadedFile::fakeImage('a.png'), UploadedFile::fakeImage('b.png')]])
            ->assertCreated()
            ->json('data');

        $this->assertCount(2, $paths);
        foreach ($paths as $path) {
            $this->assertTrue($disk->exists($path));
        }
    }

    public function test_files_from_php_superglobal_format(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, 'x');

        $request = new Request('POST', '/', files: [
            'single' => ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 1],
            'empty' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
            'many' => [
                'name' => ['a.txt', ''],
                'type' => ['text/plain', ''],
                'tmp_name' => [$tmp, ''],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
                'size' => [1, 0],
            ],
        ]);

        $this->assertInstanceOf(UploadedFile::class, $request->file('single'));
        $this->assertSame('a.txt', $request->file('single')->originalName());
        $this->assertFalse($request->file('single')->isValid(), 'not uploaded via HTTP POST');
        $this->assertNull($request->file('empty'));
        $this->assertCount(1, $request->file('many'));

        unlink($tmp);
    }
}
