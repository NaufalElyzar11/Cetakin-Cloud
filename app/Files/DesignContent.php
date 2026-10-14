<?php

namespace App\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use XMLReader;
use ZipArchive;

/** Bounded structural intake, never geometry, printability or malware certification. */
final class DesignContent
{
    public const MAX_ENTRIES = 128;

    public const MAX_EXPANDED_ENTRY = 33_554_432;

    public const MAX_EXPANDED_TOTAL = 104_857_600;

    public const MAX_EXPANSION_RATIO = 200;

    public const MAX_XML_DEPTH = 64;

    public const MAX_XML_NODES = 1_000_000;

    public const MAX_MODEL_OBJECTS = 4096;

    public const MAX_COMPONENT_REFERENCES = 16_384;

    private const CORE = 'http://schemas.microsoft.com/3dmanufacturing/core/2015/02';

    private const REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const MODEL_REL = 'http://schemas.microsoft.com/3dmanufacturing/2013/01/3dmodel';

    private const TYPES = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const MODEL_TYPE = 'application/vnd.ms-package.3dmanufacturing-3dmodel+xml';

    private float $deadline;

    /** @return array{name:string,format:string,size:int,digest:string,path:string} */
    public function inspect(UploadedFile $file, string $field): array
    {
        try {
            if (! $file->isValid() || ! is_file($file->getPathname())) {
                throw new RuntimeException('The transfer did not complete.');
            }
            $size = filesize($file->getPathname());
            if ($size === false || $size < 1 || $size > FileSelection::MAX_FILE_BYTES) {
                throw new RuntimeException('Each complete file must be at most 25 MiB.');
            }
            $name = $this->displayName($file->getClientOriginalName());
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $this->deadline = hrtime(true) / 1_000_000_000 + 15;
            match ($extension) {
                'stl' => $this->stl($file->getPathname(), $size),
                '3mf' => $this->threeMf($file->getPathname()),
                default => throw new RuntimeException('Only structurally valid STL and 3MF files are supported.'),
            };
            $digest = hash_file('sha256', $file->getPathname());
            if ($digest === false) {
                throw new RuntimeException('The file could not be read completely.');
            }

            return ['name' => $name, 'format' => strtoupper($extension), 'size' => $size, 'digest' => $digest, 'path' => $file->getPathname()];
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }

    private function displayName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';
        if ($name === '' || strlen($name) > 200 || ! mb_check_encoding($name, 'UTF-8')) {
            throw new RuntimeException('Use a valid display filename of at most 200 UTF-8 bytes.');
        }

        return $name;
    }

    private function budget(): void
    {
        if (hrtime(true) / 1_000_000_000 > $this->deadline) {
            throw new RuntimeException('The file exceeds the bounded structural-validation budget.');
        }
    }

    private function stl(string $path, int $size): void
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The file could not be read.');
        }
        try {
            $header = fread($stream, 84);
            $count = strlen($header) === 84 ? unpack('Vcount', substr($header, 80, 4))['count'] : 0;
            if ($count > 0 && 84 + 50 * $count === $size) {
                for ($i = 0; $i < $count; $i++) {
                    $record = fread($stream, 50);
                    if (strlen($record) !== 50) {
                        throw new RuntimeException('The binary STL triangle records are incomplete.');
                    }
                    foreach (unpack('g12', substr($record, 0, 48)) as $number) {
                        if (! is_finite($number)) {
                            throw new RuntimeException('STL numeric values must be finite.');
                        }
                    }
                    if ($i % 1024 === 0) {
                        $this->budget();
                    }
                }

                return;
            }
            rewind($stream);
            $state = 'solid';
            $facets = 0;
            $vertex = 0;
            $number = '[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?';
            while (($line = fgets($stream, 8194)) !== false) {
                $this->budget();
                if (strlen($line) > 8192 || preg_match('/[^\x09\x0a\x0d\x20-\x7e]/', $line)) {
                    throw new RuntimeException('ASCII STL must use bounded plain-text lines.');
                }
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $matches = [];
                $valid = match ($state) {
                    'solid' => preg_match('/^solid(?:\s.*)?$/D', $line),
                    'facet' => preg_match('/^facet\s+normal\s+('.$number.')\s+('.$number.')\s+('.$number.')$/D', $line, $matches),
                    'outer' => preg_match('/^outer\s+loop$/D', $line),
                    'vertex' => preg_match('/^vertex\s+('.$number.')\s+('.$number.')\s+('.$number.')$/D', $line, $matches),
                    'endloop' => $line === 'endloop',
                    'endfacet' => $line === 'endfacet',
                    default => false,
                };
                if ($state === 'facet' && preg_match('/^endsolid(?:\s.*)?$/D', $line) && $facets > 0) {
                    $state = 'done';

                    continue;
                }
                if (! $valid) {
                    throw new RuntimeException('The ASCII STL facet structure is invalid or incomplete.');
                }
                if (in_array($state, ['facet', 'vertex'], true)) {
                    foreach (array_slice($matches, 1) as $value) {
                        if (! is_finite((float) $value)) {
                            throw new RuntimeException('STL numeric values must be finite.');
                        }
                    }
                }
                $state = match ($state) {
                    'solid', 'endfacet' => 'facet',
                    'facet' => 'outer',
                    'outer' => 'vertex',
                    'vertex' => ++$vertex % 3 === 0 ? 'endloop' : 'vertex',
                    'endloop' => 'endfacet',
                };
                if ($line === 'endfacet') {
                    $facets++;
                }
            }
            if ($state !== 'done' || $facets < 1) {
                throw new RuntimeException('The STL must contain complete triangle records.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function threeMf(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('The 3MF ZIP container is invalid.');
        }
        try {
            if ($zip->numFiles < 3 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('The 3MF container exceeds the bounded entry policy or lacks model content.');
            }
            $entries = [];
            $expanded = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $this->budget();
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new RuntimeException('The 3MF container metadata is invalid.');
                }
                $name = $stat['name'];
                if (! $this->safePath($name) || isset($entries[strtolower($name)])) {
                    throw new RuntimeException('3MF archive paths must be safe and unambiguous.');
                }
                $opsys = $attributes = 0;
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if (($opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000)
                    || ! in_array($stat['comp_method'], [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE], true)
                    || $stat['encryption_method'] !== ZipArchive::EM_NONE) {
                    throw new RuntimeException('3MF encrypted, linked or unsupported archive entries are not supported.');
                }
                $expanded += $stat['size'];
                if ($stat['size'] > self::MAX_EXPANDED_ENTRY || $expanded > self::MAX_EXPANDED_TOTAL
                    || ($stat['size'] > 0 && ($stat['comp_size'] === 0 || $stat['size'] > max(1, $stat['comp_size']) * self::MAX_EXPANSION_RATIO))) {
                    throw new RuntimeException('The 3MF container exceeds safe expansion bounds.');
                }
                $entries[strtolower($name)] = $name;
            }
            if (! isset($entries['[content_types].xml'], $entries['_rels/.rels'])) {
                throw new RuntimeException('3MF requires package content types and a model relationship.');
            }
            $models = [];
            $modelTypes = [];
            foreach ($entries as $name) {
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $data = $this->entry($zip, $name);
                if ($name === $entries['[content_types].xml']) {
                    $this->xml($data, function (XMLReader $xml) use (&$modelTypes): void {
                        if ($xml->depth === 0 && ($xml->localName !== 'Types' || $xml->namespaceURI !== self::TYPES)) {
                            throw new RuntimeException('3MF package content types are invalid.');
                        }
                        if ($xml->localName === 'Default' && $xml->getAttribute('Extension') === 'model' && $xml->getAttribute('ContentType') === self::MODEL_TYPE) {
                            $modelTypes['*'] = true;
                        }
                        if ($xml->localName === 'Override' && $xml->getAttribute('ContentType') === self::MODEL_TYPE) {
                            $modelTypes[ltrim((string) $xml->getAttribute('PartName'), '/')] = true;
                        }
                    });
                } elseif (str_ends_with(strtolower($name), '.rels')) {
                    $this->xml($data, function (XMLReader $xml) use (&$models, $entries, $name): void {
                        if ($xml->depth === 0 && ($xml->localName !== 'Relationships' || $xml->namespaceURI !== self::REL)) {
                            throw new RuntimeException('3MF relationships are invalid.');
                        }
                        if ($xml->localName === 'Relationship') {
                            $target = (string) $xml->getAttribute('Target');
                            if ($xml->getAttribute('TargetMode') === 'External' || ! $this->safePath(ltrim($target, '/')) || str_contains($target, ':')) {
                                throw new RuntimeException('External or unsafe 3MF resource relationships are unsupported.');
                            }
                            // Package-root model relationship is authoritative. Other
                            // relationships are never resolved or opened externally.
                            if ($name === $entries['_rels/.rels'] && $xml->getAttribute('Type') === self::MODEL_REL) {
                                $target = ltrim($target, '/');
                                if (! isset($entries[strtolower($target)])) {
                                    throw new RuntimeException('The referenced 3MF model is missing.');
                                }
                                $models[] = $entries[strtolower($target)];
                            }
                        }
                    });
                } elseif (str_ends_with(strtolower($name), '.model')) {
                    $this->model($data);
                } elseif (str_ends_with(strtolower($name), '.xml')) {
                    $this->xml($data, static function (XMLReader $xml): void {});
                }
            }
            if ($models === []) {
                throw new RuntimeException('The 3MF package has no core model relationship.');
            }
            foreach ($models as $model) {
                if (! str_ends_with(strtolower($model), '.model') || (! isset($modelTypes['*']) && ! isset($modelTypes[$model]))) {
                    throw new RuntimeException('The 3MF model content type is missing.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function safePath(string $name): bool
    {
        return $name !== '' && strlen($name) <= 240 && ! preg_match('/[\\\\:\x00-\x20\x7f%]/', $name)
            && ! str_starts_with($name, '/') && ! preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $name) && ! str_contains($name, '//');
    }

    private function entry(ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        $stream = $zip->getStream($name);
        if ($stat === false || $stream === false) {
            throw new RuntimeException('A 3MF entry could not be read.');
        }
        $data = '';
        $crc = hash_init('crc32b');
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    throw new RuntimeException('A 3MF entry is incomplete.');
                }
                $data .= $chunk;
                hash_update($crc, $chunk);
                if (strlen($data) > self::MAX_EXPANDED_ENTRY || strlen($data) > $stat['size']) {
                    throw new RuntimeException('A 3MF entry exceeds its declared safe size.');
                }
                $this->budget();
            }
        } finally {
            fclose($stream);
        }
        if (strlen($data) !== $stat['size'] || hash_final($crc) !== sprintf('%08x', $stat['crc'])) {
            throw new RuntimeException('A 3MF entry has invalid integrity.');
        }

        return $data;
    }

    /** @param callable(XMLReader):void $element */
    private function xml(string $data, callable $element): void
    {
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $data)) {
            throw new RuntimeException('3MF XML declarations for entities or document types are forbidden.');
        }
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new XMLReader;
        try {
            if (! $reader->XML($data, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('3MF XML is malformed.');
            }
            $reader->setParserProperty(XMLReader::LOADDTD, false);
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);
            $nodes = 0;
            $root = false;
            while ($reader->read()) {
                $this->budget();
                if (++$nodes > self::MAX_XML_NODES || $reader->depth > self::MAX_XML_DEPTH || in_array($reader->nodeType, [XMLReader::DOC_TYPE, XMLReader::ENTITY, XMLReader::ENTITY_REF], true)) {
                    throw new RuntimeException('3MF XML exceeds safe parsing bounds.');
                }
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    $root = true;
                    $element($reader);
                }
            }
            if (! $root || libxml_get_errors() !== []) {
                throw new RuntimeException('3MF XML is malformed or incomplete.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function model(string $data): void
    {
        $resources = $build = false;
        $objects = [];
        $items = [];
        $object = null;
        $vertices = $triangles = 0;
        $ancestry = [];
        $references = 0;
        $this->xml($data, function (XMLReader $xml) use (&$resources, &$build, &$objects, &$items, &$object, &$vertices, &$triangles, &$ancestry, &$references): void {
            $ancestry = array_slice($ancestry, 0, $xml->depth);
            $ancestry[] = $xml->namespaceURI === self::CORE ? $xml->localName : 'foreign:'.$xml->localName;
            $path = implode('/', $ancestry);
            if ($xml->depth === 0 && ($xml->localName !== 'model' || $xml->namespaceURI !== self::CORE)) {
                throw new RuntimeException('The 3MF model root is invalid.');
            }
            if ($xml->getAttribute('requiredextensions') !== null && trim((string) $xml->getAttribute('requiredextensions')) !== '') {
                throw new RuntimeException('Required 3MF extensions are not supported by this bounded core-model validator.');
            }
            // Resource file paths may not cause external loading, including
            // extension attributes. Uploaded contents are never executed.
            if ($xml->hasAttributes && $xml->moveToFirstAttribute()) {
                do {
                    if (in_array(strtolower($xml->localName), ['path', 'src', 'href'], true)
                        && (! $this->safePath(ltrim($xml->value, '/')) || str_contains($xml->value, ':'))) {
                        throw new RuntimeException('External 3MF model resources are unsupported.');
                    }
                } while ($xml->moveToNextAttribute());
                $xml->moveToElement();
            }
            if ($xml->namespaceURI !== self::CORE) {
                return;
            }
            if ($path === 'model/resources') {
                if ($resources) {
                    throw new RuntimeException('3MF contains duplicate resource sections.');
                }
                $resources = true;
            } elseif ($path === 'model/build') {
                if ($build) {
                    throw new RuntimeException('3MF contains duplicate build sections.');
                }
                $build = true;
            } elseif ($path === 'model/resources/object') {
                $object = $this->positiveId($xml->getAttribute('id'));
                if (isset($objects[$object]) || count($objects) >= self::MAX_MODEL_OBJECTS) {
                    throw new RuntimeException('3MF object identities must be unique.');
                }
                $type = $xml->getAttribute('type') ?? 'model';
                if (! in_array($type, ['model', 'solidsupport', 'support', 'surface', 'other'], true)) {
                    throw new RuntimeException('The 3MF object type is invalid.');
                }
                $objects[$object] = ['vertices' => 0, 'triangles' => 0, 'components' => [], 'kind' => null, 'type' => $type];
                $vertices = $triangles = 0;
            } elseif (in_array($path, ['model/resources/object/mesh', 'model/resources/object/components'], true) && $object !== null) {
                if ($objects[$object]['kind'] !== null) {
                    throw new RuntimeException('3MF object content must be one mesh or component definition.');
                }
                $objects[$object]['kind'] = $xml->localName;
            } elseif ($path === 'model/resources/object/mesh/vertices/vertex' && $object !== null) {
                foreach (['x', 'y', 'z'] as $coordinate) {
                    $value = $xml->getAttribute($coordinate);
                    if ($value === null || ! is_numeric($value) || ! is_finite((float) $value)) {
                        throw new RuntimeException('3MF vertex coordinates must be finite numbers.');
                    }
                }
                $objects[$object]['vertices'] = ++$vertices;
            } elseif ($path === 'model/resources/object/mesh/triangles/triangle' && $object !== null) {
                $indices = [];
                foreach (['v1', 'v2', 'v3'] as $index) {
                    $value = $xml->getAttribute($index);
                    if ($value === null || ! preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) || strlen($value) > 8 || (int) $value >= $vertices) {
                        throw new RuntimeException('3MF triangle vertex references are invalid.');
                    }
                    $indices[] = (int) $value;
                }
                if (count(array_unique($indices)) !== 3) {
                    throw new RuntimeException('3MF triangle vertex indices must be distinct.');
                }
                $objects[$object]['triangles'] = ++$triangles;
            } elseif ($path === 'model/resources/object/components/component' && $object !== null) {
                if (++$references > self::MAX_COMPONENT_REFERENCES) {
                    throw new RuntimeException('3MF exceeds bounded component references.');
                }
                $objects[$object]['components'][] = $this->positiveId($xml->getAttribute('objectid'));
            } elseif ($path === 'model/build/item') {
                if (++$references > self::MAX_COMPONENT_REFERENCES) {
                    throw new RuntimeException('3MF exceeds bounded build references.');
                }
                $items[] = $this->positiveId($xml->getAttribute('objectid'));
            }
        });
        if (! $resources || ! $build || $objects === [] || $items === []) {
            throw new RuntimeException('3MF requires model resources and a nonempty build.');
        }
        foreach ($objects as $record) {
            // Core object kinds/counts are structural facts, not a calculation
            // of manifold geometry, volume, suitability or printability.
            $minimumTriangles = in_array($record['type'], ['model', 'solidsupport'], true) ? 4 : 1;
            if (($record['kind'] === 'mesh' && ($record['vertices'] < 3 || $record['triangles'] < $minimumTriangles))
                || ($record['kind'] === 'components' && $record['components'] === []) || $record['kind'] === null) {
                throw new RuntimeException('3MF objects require structurally complete mesh content or internal components.');
            }
        }
        $visit = function (int $id, array $ancestors) use (&$visit, $objects): void {
            if (! isset($objects[$id]) || isset($ancestors[$id]) || count($ancestors) > self::MAX_XML_DEPTH) {
                throw new RuntimeException('3MF build/component references are missing, cyclic or too deep.');
            }
            if ($objects[$id]['kind'] === 'mesh' && $objects[$id]['type'] === 'other') {
                throw new RuntimeException('3MF build items cannot reference an object of type other.');
            }
            $ancestors[$id] = true;
            foreach ($objects[$id]['components'] as $child) {
                $this->budget();
                $visit($child, $ancestors);
            }
        };
        foreach ($items as $item) {
            $visit($item, []);
        }
    }

    private function positiveId(?string $input): int
    {
        if ($input === null || ! preg_match('/^[1-9][0-9]*$/D', $input) || strlen($input) > 10 || (int) $input > 2147483647) {
            throw new RuntimeException('3MF object references must be positive bounded identifiers.');
        }

        return (int) $input;
    }
}
