<?php

namespace Tests\Database\Intake;

use App\Files\DesignContent;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\SyntheticDesigns;
use ZipArchive;

class DesignContentTest extends TestCase
{
    use SyntheticDesigns;

    protected function tearDown(): void
    {
        $this->removeDesignFixtures();
        parent::tearDown();
    }

    public function test_structural_ascii_binary_stl_and_core_3mf_preserve_exact_content_identity(): void
    {
        foreach ([$this->fixture($this->asciiStl()), $this->fixture($this->binaryStl()), $this->zipFixture($this->packageParts())] as $file) {
            $descriptor = (new DesignContent)->inspect($file, 'files.0');
            $this->assertSame(hash_file('sha256', $file->getPathname()), $descriptor['digest']);
            $this->assertSame(filesize($file->getPathname()), $descriptor['size']);
        }
    }

    public function test_real_file_at_25_mib_is_inclusive_and_one_byte_over_is_denied(): void
    {
        $file = $this->fixture($this->asciiStl(), 'boundary.stl', 26_214_400);
        $this->assertSame(26_214_400, (new DesignContent)->inspect($file, 'files.0')['size']);
        $this->expectException(ValidationException::class);
        (new DesignContent)->inspect($this->fixture($this->asciiStl(), 'over.stl', 26_214_401), 'files.0');
    }

    private function denied(UploadedFile $file): void
    {
        try {
            (new DesignContent)->inspect($file, 'files.0');
            $this->fail('Unsafe or invalid synthetic design must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('files.0', $exception->errors());
        }
    }

    public function test_malformed_stl_content_mismatch_and_incomplete_transfer_are_denied(): void
    {
        $this->denied($this->fixture('not an STL', 'renamed.stl'));
        $this->denied($this->fixture(substr($this->binaryStl(), 0, -1)));
        $this->denied($this->fixture(str_replace('vertex 1 0 0', 'vertex 1e999 0 0', $this->asciiStl())));
        $this->denied($this->fixture($this->asciiStl(), 'unsupported.obj'));
        $this->denied($this->fixture($this->asciiStl(), 'renamed.3mf'));
        $file = $this->fixture($this->asciiStl());
        $this->denied(new UploadedFile($file->getPathname(), 'partial.stl', null, UPLOAD_ERR_PARTIAL, true));
    }

    public function test_malformed_archive_missing_models_unsafe_paths_and_external_resources_are_denied(): void
    {
        $this->denied($this->fixture('PK malformed', 'broken.3mf'));
        $parts = $this->packageParts();
        unset($parts['3D/model.model']);
        $this->denied($this->zipFixture($parts));
        foreach (['../escaped.xml', '/absolute.xml', '3D\\evil.xml', '3D/%2e%2e/evil.xml'] as $path) {
            $this->denied($this->zipFixture([...$this->packageParts(), $path => '<unsafe/>']));
        }
        $parts = $this->packageParts();
        $parts['_rels/.rels'] = str_replace('Target="/3D/model.model"', 'Target="https://example.test/model" TargetMode="External"', $parts['_rels/.rels']);
        $this->denied($this->zipFixture($parts));
        $parts = $this->packageParts();
        $parts['3D/model.model'] = str_replace('v3="2"', 'v3="100"', $parts['3D/model.model']);
        $this->denied($this->zipFixture($parts));
        $parts = $this->packageParts();
        $parts['3D/model.model'] = str_replace(['<mesh>', '</mesh>', '<vertices>', '</vertices>', '<triangles>', '</triangles>'], ['<garbage>', '</garbage>', '<garbage>', '</garbage>', '<garbage>', '</garbage>'], $parts['3D/model.model']);
        $this->denied($this->zipFixture($parts));
    }

    public function test_xml_entities_malformed_xml_depth_and_zip_expansion_abuse_are_denied(): void
    {
        foreach (['<!DOCTYPE model [<!ENTITY external SYSTEM "file:///etc/passwd">]>', '<!DOCTYPE model [<!ENTITY x "xxxxxxxx">]>'] as $declaration) {
            $parts = $this->packageParts();
            $parts['3D/model.model'] = $declaration.str_replace('<?xml version="1.0"?>', '', $parts['3D/model.model']);
            $this->denied($this->zipFixture($parts));
        }
        $parts = $this->packageParts();
        $parts['3D/model.model'] = '<model><broken>';
        $this->denied($this->zipFixture($parts));
        $this->denied($this->zipFixture([...$this->packageParts(), 'deep.xml' => str_repeat('<x>', 70).str_repeat('</x>', 70)]));
        $this->denied($this->zipFixture([...$this->packageParts(), 'bomb.bin' => str_repeat('A', 100_000)]));
        $parts = $this->packageParts();
        for ($i = 0; $i < 126; $i++) {
            $parts['metadata'.$i.'.bin'] = 'x';
        }
        $this->denied($this->zipFixture($parts, compression: ZipArchive::CM_STORE));
        $this->denied($this->zipFixture([...$this->packageParts(), 'huge.bin' => str_repeat('X', DesignContent::MAX_EXPANDED_ENTRY + 1)]));
    }

    public function test_expanded_entry_and_total_bounds_are_exercised_below_raw_upload_and_ratio_limits(): void
    {
        foreach ([[DesignContent::MAX_EXPANDED_ENTRY + 1], [26_214_400, 26_214_400, 26_214_400, 26_214_400]] as $sizes) {
            $file = $this->expandedZipFixture($sizes);
            $this->assertLessThan(26_214_400, filesize($file->getPathname()));
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($file->getPathname()) === true);
            foreach ($sizes as $index => $size) {
                $stat = $zip->statName('bounded-'.$index.'.bin');
                $this->assertLessThanOrEqual(200, $stat['size'] / $stat['comp_size']);
            }
            $zip->close();
            try {
                (new DesignContent)->inspect($file, 'files.0');
                $this->fail('Expanded archive-byte bounds must reject this otherwise bounded ZIP.');
            } catch (ValidationException $exception) {
                $this->assertSame(['The 3MF container exceeds safe expansion bounds.'], $exception->errors()['files.0']);
            }
        }
    }

    public function test_core_object_types_and_triangle_counts_are_structural_checks_without_a_printability_claim(): void
    {
        $parts = $this->packageParts();
        $oneTriangle = preg_replace('~<triangles>.*?</triangles>~', '<triangles><triangle v1="0" v2="1" v3="2"/></triangles>', $parts['3D/model.model']);
        foreach (['model', 'solidsupport'] as $type) {
            $this->denied($this->zipFixture([...$parts, '3D/model.model' => str_replace('type="model"', 'type="'.$type.'"', $oneTriangle)]));
        }
        $this->denied($this->zipFixture([...$parts, '3D/model.model' => str_replace(' type="model"', '', $oneTriangle)]));
        $this->denied($this->zipFixture([...$parts, '3D/model.model' => str_replace('type="model"', 'type="unknown"', $parts['3D/model.model'])]));
        $this->denied($this->zipFixture([...$parts, '3D/model.model' => str_replace('type="model"', 'type="other"', $parts['3D/model.model'])]));
        $this->denied($this->zipFixture([...$parts, '3D/model.model' => str_replace('v1="0" v2="2" v3="1"', 'v1="0" v2="0" v3="1"', $parts['3D/model.model'])]));
        $surface = $this->zipFixture([...$parts, '3D/model.model' => str_replace('type="model"', 'type="surface"', $oneTriangle)]);
        $this->assertSame('3MF', (new DesignContent)->inspect($surface, 'files.0')['format']);
        $default = $this->zipFixture([...$parts, '3D/model.model' => str_replace(' type="model"', '', $parts['3D/model.model'])]);
        $this->assertSame('3MF', (new DesignContent)->inspect($default, 'files.0')['format']);
    }
}
