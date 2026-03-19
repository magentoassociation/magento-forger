<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\LabelController;
use App\Services\GitHub\GitHubService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Mockery;
use OpenSearch\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class LabelControllerTest extends TestCase
{
    private const REPO_OWNER = 'configured-owner';

    private const REPO_NAME = 'configured-repo';

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['github.repo' => self::REPO_OWNER.'/'.self::REPO_NAME]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (file_exists($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        Mockery::close();

        parent::tearDown();
    }

    public function test_list_all_labels_groups_labels_by_prefix_and_sorts_prefixes(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->once()->with(Mockery::on(static function (array $params): bool {
            return $params['index'] === 'github-issues'
                && $params['body']['query']['term']['is_open'] === true
                && $params['body']['aggs']['by_label']['terms']['field'] === 'labels.keyword';
        }))->andReturn([
            'aggregations' => [
                'by_label' => [
                    'buckets' => [
                        ['key' => 'Standalone', 'doc_count' => 1],
                        ['key' => 'Component: Checkout', 'doc_count' => 5],
                        ['key' => 'Area: Frontend', 'doc_count' => 3],
                    ],
                ],
            ],
        ]);

        $view = $this->createController()->listAllLabels($client);

        $this->assertSame('labels.allLabels', $view->name());
        $this->assertTrue((static function (array $labels): bool {
            return array_keys($labels) === ['Area', 'Component', 'no_prefix']
                && $labels['Area'][0] === ['label' => 'Area: Frontend', 'count' => 3]
                && $labels['Component'][0] === ['label' => 'Component: Checkout', 'count' => 5]
                && $labels['no_prefix'][0] === ['label' => 'Standalone', 'count' => 1];
        })($view->getData()['labels']));
    }

    public function test_list_prs_without_component_label_builds_monthly_ranges(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->once()->with(Mockery::on(static function (array $params): bool {
            return $params['index'] === 'github-pull-requests'
                && $params['body']['query']['bool']['must'][0]['term']['is_open'] === true
                && $params['body']['query']['bool']['must_not'][0]['regexp']['labels.keyword'] === 'Component:.*';
        }))->andReturn([
            'aggregations' => [
                'by_year' => [
                    'buckets' => [
                        [
                            'key_as_string' => '2024',
                            'doc_count' => 4,
                            'by_month' => [
                                'buckets' => [
                                    ['key_as_string' => '02', 'doc_count' => 1],
                                    ['key_as_string' => '11', 'doc_count' => 3],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $view = $this->createController()->listPrWithoutComponentLabel($client);

        $this->assertSame('labels.prsWithoutComponentLabel', $view->name());
        $this->assertTrue((static function (array $prs): bool {
            return $prs['2024']['total'] === 4
                && $prs['2024']['months']['01'] === [
                    'month_number' => '01',
                    'total' => 0,
                    'start' => null,
                    'end' => null,
                ]
                && $prs['2024']['months']['02']['total'] === 1
                && $prs['2024']['months']['02']['start'] === '2024-02-01T00:00:00Z'
                && $prs['2024']['months']['02']['end'] === '2024-02-29T23:59:59Z'
                && $prs['2024']['months']['11']['total'] === 3
                && $prs['2024']['months']['11']['start'] === '2024-11-01T00:00:00Z'
                && $prs['2024']['months']['11']['end'] === '2024-11-30T23:59:59Z';
        })($view->getData()['prs']));
    }

    public function test_process_labels_page_is_available_to_admins(): void
    {
        $view = $this->createController()->processLabels();

        $this->assertSame('labels.processLabels', $view->name());
    }

    public function test_upload_labels_records_an_error_when_create_returns_zero(): void
    {
        Log::spy();

        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('createLabel')->once()->with(
            self::REPO_OWNER,
            self::REPO_NAME,
            'Area: Foo'
        )->andReturn(0);
        $github->shouldReceive('getLastLabelOperationError')->once()->andReturn([
            'operation' => 'create',
            'status' => 'skipped',
            'message' => "Label 'Area: Foo' already exists.",
        ]);

        $this->app->instance(GitHubService::class, $github);

        $response = $this->post(route('labels-uploadLabels'), [
            'label_sheet' => $this->createLabelSpreadsheet([
                ['A' => 'Area: Foo'],
            ]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', function (string $message): bool {
            return str_contains($message, 'Label processing failed.')
                && str_contains($message, 'Created Labels: 0')
                && str_contains($message, "Skipped creating label 'Area: Foo': Label 'Area: Foo' already exists.");
        });
        $response->assertSessionMissing('success');
        $response->assertSessionMissing('warning');

        Log::shouldHaveReceived('error')->once()->with(
            'GitHub label creation returned 0.',
            Mockery::on(static function (array $context): bool {
                return $context['label'] === 'Area: Foo'
                    && $context['service_error']['status'] === 'skipped';
            })
        );
    }

    public function test_upload_labels_records_an_error_when_rename_returns_zero(): void
    {
        Log::spy();

        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('renameLabel')->once()->with(
            self::REPO_OWNER,
            self::REPO_NAME,
            'Area: Old',
            'Area: New'
        )->andReturn(0);
        $github->shouldReceive('getLastLabelOperationError')->once()->andReturn([
            'operation' => 'rename',
            'status' => 'failed',
            'message' => 'GitHub rejected the rename request.',
        ]);

        $this->app->instance(GitHubService::class, $github);

        $response = $this->post(route('labels-uploadLabels'), [
            'label_sheet' => $this->createLabelSpreadsheet([
                ['A' => 'Area: Old', 'E' => 'Area: New'],
            ]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', function (string $message): bool {
            return str_contains($message, 'Label processing failed.')
                && str_contains($message, 'Renamed Labels: 0')
                && str_contains($message, "Failed to rename 'Area: Old' to 'Area: New': GitHub rejected the rename request.");
        });
        $response->assertSessionMissing('success');
        $response->assertSessionMissing('warning');

        Log::shouldHaveReceived('error')->once()->with(
            'GitHub label rename returned 0.',
            Mockery::on(static function (array $context): bool {
                return $context['old_name'] === 'Area: Old'
                    && $context['new_name'] === 'Area: New'
                    && $context['service_error']['status'] === 'failed';
            })
        );
    }

    public function test_upload_labels_records_a_warning_when_processing_is_partially_successful(): void
    {
        Log::spy();

        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('createLabel')->once()->with(
            self::REPO_OWNER,
            self::REPO_NAME,
            'Area: Good'
        )->andReturn(1);
        $github->shouldReceive('createLabel')->once()->with(
            self::REPO_OWNER,
            self::REPO_NAME,
            'Area: Bad'
        )->andReturn(0);
        $github->shouldReceive('getLastLabelOperationError')->once()->andReturn([
            'operation' => 'create',
            'status' => 'failed',
            'message' => 'GitHub rejected the create request.',
        ]);

        $this->app->instance(GitHubService::class, $github);

        $response = $this->post(route('labels-uploadLabels'), [
            'label_sheet' => $this->createLabelSpreadsheet([
                ['A' => 'Area: Good'],
                ['A' => 'Area: Bad'],
            ]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning', function (string $message): bool {
            return str_contains($message, 'Labels were processed with some errors.')
                && str_contains($message, 'Created Labels: 1')
                && str_contains($message, 'Renamed Labels: 0')
                && str_contains($message, "Failed to create label 'Area: Bad': GitHub rejected the create request.");
        });
        $response->assertSessionMissing('success');
        $response->assertSessionMissing('error');
    }

    public function test_upload_labels_records_success_when_creates_and_renames_succeed_across_tabs(): void
    {
        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('createLabel')->once()->with(
            self::REPO_OWNER,
            self::REPO_NAME,
            'Area: New'
        )->andReturn(1);
        $github->shouldReceive('renameLabel')->once()->with(
            self::REPO_OWNER,
            self::REPO_NAME,
            'Component: Old',
            'Component: New'
        )->andReturn(1);

        $this->app->instance(GitHubService::class, $github);

        $response = $this->post(route('labels-uploadLabels'), [
            'label_sheet' => $this->createLabelSpreadsheet(
                [
                    ['A' => 'Area: New'],
                ],
                [
                    ['A' => 'Component: Old', 'E' => 'Component: New'],
                ]
            ),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', static function (string $message): bool {
            return str_contains($message, 'Labels were processed successfully.')
                && str_contains($message, 'Created Labels: 1')
                && str_contains($message, 'Renamed Labels: 1')
                && ! str_contains($message, 'Errors:');
        });
        $response->assertSessionMissing('warning');
        $response->assertSessionMissing('error');
    }

    public function test_upload_labels_throws_when_repo_configuration_is_missing(): void
    {
        config(['github.repo' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GitHub repository is not configured');

        $this->createController()->exposedGetRepo();
    }

    public function test_upload_labels_throws_when_repo_configuration_is_invalid(): void
    {
        config(['github.repo' => 'invalid/repo/value']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository format');

        $this->createController()->exposedGetRepo();
    }

    private function createLabelSpreadsheet(array $areaRows, array $componentRows = []): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $this->populateLabelSheet($spreadsheet->getActiveSheet(), 'area', $areaRows);
        $this->populateLabelSheet($spreadsheet->createSheet(), 'component', $componentRows);

        $path = tempnam(sys_get_temp_dir(), 'labels');
        if ($path === false) {
            self::fail('Failed to allocate a temporary file for the spreadsheet test fixture.');
        }

        $xlsxPath = $path.'.xlsx';
        unlink($path);
        (new Xlsx($spreadsheet))->save($xlsxPath);
        $this->temporaryFiles[] = $xlsxPath;

        return new UploadedFile(
            $xlsxPath,
            'labels.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function populateLabelSheet(Worksheet $sheet, string $title, array $rows): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray(['Label', 'B', 'C', 'Keep', 'Rename', 'Replace With'], null, 'A1');

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            foreach ($row as $column => $value) {
                $sheet->setCellValue("{$column}{$rowNumber}", $value);
            }
        }
    }

    private function createController(): LabelController
    {
        return new class extends LabelController
        {
            public function exposedGetRepo(): array
            {
                return $this->getRepo();
            }
        };
    }
}
