<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

trait SyntheticDesigns
{
    /** @var list<string> */
    private array $designFixturePaths = [];

    private function asciiStl(): string
    {
        return "solid synthetic\nfacet normal 0 0 1\nouter loop\nvertex 0 0 0\nvertex 1 0 0\nvertex 0 1 0\nendloop\nendfacet\nendsolid synthetic\n";
    }

    private function binaryStl(): string
    {
        return str_pad('Synthetic binary fixture', 80, "\0").pack('V', 1).pack('g12v', 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0);
    }

    private function modelXml(): string
    {
        return '<?xml version="1.0"?><model xmlns="http://schemas.microsoft.com/3dmanufacturing/core/2015/02" unit="millimeter"><resources><object id="1" type="model"><mesh><vertices><vertex x="0" y="0" z="0"/><vertex x="1" y="0" z="0"/><vertex x="0" y="1" z="0"/><vertex x="0" y="0" z="1"/></vertices><triangles><triangle v1="0" v2="2" v3="1"/><triangle v1="0" v2="1" v3="3"/><triangle v1="0" v2="3" v3="2"/><triangle v1="1" v2="2" v3="3"/></triangles></mesh></object></resources><build><item objectid="1"/></build></model>';
    }

    /** @return array<string,string> */
    private function packageParts(): array
    {
        return [
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="model" ContentType="application/vnd.ms-package.3dmanufacturing-3dmodel+xml"/></Types>',
            '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rel1" Type="http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel" Target="/3D/model.model"/></Relationships>',
            '3D/model.model' => $this->modelXml(),
        ];
    }

    /** @param array<string,string> $parts */
    private function zipFixture(array $parts, string $name = 'synthetic.3mf', int $compression = ZipArchive::CM_DEFLATE): UploadedFile
    {
        $path = $this->fixturePath();
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Synthetic ZIP creation failed.');
        }
        foreach ($parts as $key => $content) {
            $zip->addFromString($key, $content);
            $zip->setCompressionName($key, $compression);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/octet-stream', null, true);
    }

    private function fixture(string $bytes, string $name = 'synthetic.stl', ?int $size = null): UploadedFile
    {
        $path = $this->fixturePath();
        $stream = fopen($path, 'wb');
        fwrite($stream, $bytes);
        if ($size !== null) {
            $remaining = $size - strlen($bytes);
            while ($remaining > 0) {
                $amount = min(8191, $remaining);
                fwrite($stream, str_repeat(' ', $amount - 1)."\n");
                $remaining -= $amount;
            }
        }
        fclose($stream);

        return new UploadedFile($path, $name, 'application/octet-stream', null, true);
    }

    /** @param list<int> $expandedSizes */
    private function expandedZipFixture(array $expandedSizes): UploadedFile
    {
        $path = $this->fixturePath();
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Synthetic ZIP creation failed.');
        }
        foreach ($this->packageParts() as $key => $content) {
            $zip->addFromString($key, $content);
        }
        foreach ($expandedSizes as $index => $size) {
            $source = $this->fixturePath();
            $stream = fopen($source, 'wb');
            $remaining = $size;
            $counter = 0;
            while ($remaining > 0) {
                // About 1:16 compressibility; varying deterministic entropy
                // prevents the ratio guard from masking the expanded-byte guard.
                $entropy = '';
                for ($part = 0; $part < 128; $part++) {
                    $entropy .= hash('sha256', $index.':'.$counter.':'.$part, true);
                }
                $block = $entropy.str_repeat("\0", 61_440);
                $amount = min($remaining, strlen($block));
                fwrite($stream, substr($block, 0, $amount));
                $remaining -= $amount;
                $counter++;
            }
            fclose($stream);
            $zip->addFile($source, 'bounded-'.$index.'.bin');
        }
        $zip->close();

        return new UploadedFile($path, 'expanded.3mf', 'application/octet-stream', null, true);
    }

    private function fixturePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cetakin-synthetic-');
        $this->designFixturePaths[] = $path;

        return $path;
    }

    private function removeDesignFixtures(): void
    {
        foreach ($this->designFixturePaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->designFixturePaths = [];
    }
}
