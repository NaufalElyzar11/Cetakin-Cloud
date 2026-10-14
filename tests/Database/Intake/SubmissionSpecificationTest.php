<?php

namespace Tests\Database\Intake;

use App\Files\FileSelection;
use App\Intake\SubmissionSpecification;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class SubmissionSpecificationTest extends TestCase
{
    public function test_quantity_is_a_canonical_decimal_count_of_complete_sets_with_only_a_representation_bound(): void
    {
        foreach (['1', '3', '9007199254740993', '9223372036854775807'] as $quantity) {
            $this->assertSame($quantity, SubmissionSpecification::quantity($quantity));
        }
        FileSelection::validateSizes([1, 1, 1]);
        $this->assertSame('3', SubmissionSpecification::quantity('3')); // Not nine components.
    }

    public static function invalidQuantities(): array
    {
        return array_map(fn ($value) => [$value], ['0', '-1', '1.5', '1e3', '01', ' 1', '9223372036854775808', '999999999999999999999', 1.5, [], null]);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_or_unrepresentable_quantity_is_rejected(mixed $quantity): void
    {
        $this->expectException(ValidationException::class);
        SubmissionSpecification::quantity($quantity);
    }

    public function test_inclusive_binary_file_limits_do_not_count_components_as_quantity(): void
    {
        FileSelection::validateSizes([26_214_400, 26_214_400, 26_214_400]);
        FileSelection::validateSizes([1, 1, 1, 1, 1]);
        $this->assertSame(78_643_200, FileSelection::MAX_TOTAL_BYTES);
    }

    public static function invalidSelections(): array
    {
        return [[[]], [[1, 1, 1, 1, 1, 1]], [[26_214_401]], [[26_214_400, 26_214_400, 26_214_400, 1]], [[0]]];
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_byte_or_count_bounds_are_rejected(array $sizes): void
    {
        $this->expectException(ValidationException::class);
        FileSelection::validateSizes($sizes);
    }
}
