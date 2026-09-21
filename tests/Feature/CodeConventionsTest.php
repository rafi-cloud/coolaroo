<?php

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ReflectionClass;
use SplFileInfo;
use Tests\TestCase;

/**
 * T223, NFR19. The project coding conventions, as assertions.
 *
 * A convention nobody checks is a convention that decays: these fail the suite
 * rather than waiting to be noticed in review.
 */
class CodeConventionsTest extends TestCase
{
    public function test_the_code_is_formatted_to_the_pint_preset(): void
    {
        $pint = base_path('vendor/bin/pint');

        if (! File::exists($pint)) {
            $this->markTestSkipped('Pint is not installed.');
        }

        $result = Process::path(base_path())->timeout(300)->run([PHP_BINARY, $pint, '--test']);

        $this->assertTrue($result->successful(),
            "NFR19: run `vendor/bin/pint` — these files are not formatted:\n".$result->output());
    }

    public function test_every_service_docblock_cites_the_rules_it_implements(): void
    {
        $missing = [];

        foreach (File::files(app_path('Services')) as $file) {
            $class = 'App\\Services\\'.$file->getBasename('.php');
            $docblock = (new ReflectionClass($class))->getDocComment() ?: '';

            if (! preg_match('/\b(FR\d+|BR\d+|NFR\d+|UC\d+|\d{2}\.\d)/', $docblock)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing,
            'Service layer rule: a service must name the FR/BR it implements in its class docblock. Missing: '
            .implode(', ', $missing));
    }

    public function test_controllers_hold_no_transactions(): void
    {
        $offenders = $this->phpFilesIn(app_path('Http/Controllers'))
            ->filter(fn (SplFileInfo $file) => str_contains(File::get($file->getPathname()), 'DB::transaction'))
            ->map(fn (SplFileInfo $file) => $file->getFilename())
            ->values()
            ->all();

        $this->assertSame([], $offenders,
            'Controller rule: controllers stay thin — a transaction belongs in a service. Found in: '
            .implode(', ', $offenders));
    }

    public function test_status_columns_are_written_only_inside_services_and_enums(): void
    {
        $offenders = $this->phpFilesIn(app_path())
            ->reject(fn (SplFileInfo $file) => str_contains($file->getPath(), 'Services')
                || str_contains($file->getPath(), 'Enums'))
            ->filter(function (SplFileInfo $file) {
                $source = File::get($file->getPathname());

                return str_contains($source, "forceFill(['status'")
                    || str_contains($source, "update(['status'");
            })
            ->map(fn (SplFileInfo $file) => $file->getFilename())
            ->values()
            ->all();

        $this->assertSame([], $offenders,
            'Status rule: a status change goes through the enum transition map in a service. Found in: '
            .implode(', ', $offenders));
    }

    public function test_views_use_blade_components_rather_than_template_inheritance(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = File::get($file->getPathname());

            foreach (['@extends', '@section', '@yield', '@include'] as $directive) {
                if (str_contains($source, $directive)) {
                    $offenders[] = $file->getFilename().' ('.$directive.')';
                }
            }
        }

        $this->assertSame([], $offenders,
            'SDD 8.7: pages wrap content in <x-layouts.*> components. Found: '
            .implode(', ', $offenders));
    }

    /** @return Collection<int, SplFileInfo> */
    private function phpFilesIn(string $directory)
    {
        return collect(File::allFiles($directory))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php');
    }
}
