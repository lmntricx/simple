<?php

namespace Tests\Unit\Storage;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simple\Http\UploadedFile;
use Simple\Storage\Disk;

class DiskTest extends TestCase
{
    private string $root;
    private Disk $disk;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/simple_disk_test_' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->disk = new Disk($this->root, 'http://x.test/uploads');
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->root);
    }

    public function test_put_get_exists_delete(): void
    {
        $this->disk->put('notes/a.txt', 'hello');

        $this->assertTrue($this->disk->exists('notes/a.txt'));
        $this->assertSame('hello', $this->disk->get('notes/a.txt'));
        $this->assertSame(5, $this->disk->size('notes/a.txt'));
        $this->assertSame(['notes/a.txt'], $this->disk->files('notes'));

        $this->assertTrue($this->disk->delete('notes/a.txt'));
        $this->assertFalse($this->disk->exists('notes/a.txt'));
        $this->assertNull($this->disk->get('notes/a.txt'));
    }

    public function test_url(): void
    {
        $this->assertSame('http://x.test/uploads/avatars/my%20pic.png', $this->disk->url('avatars/my pic.png'));
    }

    public function test_private_disks_have_no_url(): void
    {
        $this->expectException(RuntimeException::class);
        (new Disk($this->root))->url('a.txt');
    }

    public static function traversals(): array
    {
        return [
            ['../secret.txt'],
            ['a/../../secret.txt'],
            ['..\\secret.txt'],
            ["a\0.txt"],
            ['C:/Windows/win.ini'],
        ];
    }

    #[DataProvider('traversals')]
    public function test_paths_cannot_escape_the_disk(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->disk->put($path, 'x');
    }

    public function test_normalize(): void
    {
        $this->assertSame('a/b/c.txt', Disk::normalize('/a/./b//c.txt'));
        $this->assertSame('', Disk::normalize('/'));
    }

    public function test_put_file_uses_a_random_name_and_the_detected_extension(): void
    {
        $path = $this->disk->putFile('avatars', UploadedFile::fakeImage('../../me.php.jpg'));

        $this->assertMatchesRegularExpression('#^avatars/[0-9a-f]{40}\.png$#', $path);
        $this->assertTrue($this->disk->exists($path));
    }

    public function test_a_php_script_disguised_as_an_image_is_never_stored_as_php(): void
    {
        $file = UploadedFile::fake('photo.jpg', '<?php system($_GET["c"]); ?>', 'image/jpeg');

        $this->assertSame('text/x-php', $file->mimeType());
        $this->assertFalse($file->isImage());
        $this->assertStringEndsWith('.bin', $this->disk->putFile('uploads', $file));
    }

    public function test_put_file_as_rejects_path_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->disk->putFileAs('avatars', UploadedFile::fakeImage(), '../evil.png');
    }

    public function test_download_response(): void
    {
        $this->disk->put('reports/q3.csv', "a,b\n1,2\n");
        $response = $this->disk->download('reports/q3.csv', 'Résumé "Q3".csv');

        $this->assertSame("a,b\n1,2\n", $response->content());
        $this->assertSame('8', $response->headers()['Content-Length']);
        $this->assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        $this->assertSame(
            'attachment; filename="R_sum_ Q3.csv"; filename*=UTF-8\'\'R%C3%A9sum%C3%A9%20Q3.csv',
            $response->headers()['Content-Disposition'],
        );
    }
}
