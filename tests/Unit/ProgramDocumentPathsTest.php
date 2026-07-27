<?php

namespace Tests\Unit;

use App\Support\ProgramDocumentPaths;
use PHPUnit\Framework\TestCase;

class ProgramDocumentPathsTest extends TestCase
{
    public function test_normalizes_string_path(): void
    {
        $this->assertSame(['foo/bar.pdf'], ProgramDocumentPaths::normalize('foo/bar.pdf'));
    }

    public function test_normalizes_array_of_strings(): void
    {
        $this->assertSame(
            ['a.pdf', 'b.pdf'],
            ProgramDocumentPaths::normalize(['a.pdf', 'b.pdf']),
        );
    }

    public function test_normalizes_nested_path_objects(): void
    {
        $this->assertSame(
            ['program-documents/beneficiary/1/file.pdf'],
            ProgramDocumentPaths::normalize([['path' => 'program-documents/beneficiary/1/file.pdf']]),
        );
    }

    public function test_strips_storage_prefix(): void
    {
        $this->assertSame(
            ['program-documents/beneficiary/1/file.pdf'],
            ProgramDocumentPaths::normalize(['storage/program-documents/beneficiary/1/file.pdf']),
        );
    }
}
