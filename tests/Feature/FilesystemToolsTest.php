<?php

use App\AI\Tools\GlobFiles;
use App\AI\Tools\ListFiles;
use App\AI\Tools\ReadFile;
use App\AI\Tools\RunBashScript;
use App\AI\Tools\SearchInFiles;
use App\AI\Tools\WriteFile;
use Illuminate\Support\Facades\File;

function filesystemToolsPath(string $suffix = ''): string
{
    $basePath = 'storage/framework/testing/filesystem-tools';

    if ($suffix === '') {
        return $basePath;
    }

    return sprintf('%s/%s', $basePath, ltrim($suffix, '/'));
}

afterEach(function () {
    File::deleteDirectory(base_path(filesystemToolsPath()));
});

test('write file and read file tools work together', function () {
    $writeFile = new WriteFile;
    $readFile = new ReadFile;

    $writeResult = $writeFile->use([
        'path' => filesystemToolsPath('notes.txt'),
        'content' => 'agent note',
    ]);

    $readResult = $readFile->use([
        'path' => filesystemToolsPath('notes.txt'),
    ]);

    expect($writeResult)->toContain('Wrote 10 bytes');
    expect($readResult)->toBe('agent note');
});

test('list files returns directories and files from a relative path', function () {
    File::ensureDirectoryExists(base_path(filesystemToolsPath('nested')));
    file_put_contents(base_path(filesystemToolsPath('nested/todo.md')), '# TODO');

    $listFiles = new ListFiles;

    $result = $listFiles->use([
        'path' => filesystemToolsPath(),
    ]);

    expect($result)->toBeArray();
    expect($result['directories'])->toContain(filesystemToolsPath('nested/'));
    expect($result['files'])->toBeArray();
});

test('glob files returns matching project-relative paths', function () {
    File::ensureDirectoryExists(base_path(filesystemToolsPath('docs')));
    file_put_contents(base_path(filesystemToolsPath('docs/one.md')), '# One');
    file_put_contents(base_path(filesystemToolsPath('docs/two.txt')), 'Two');

    $globFiles = new GlobFiles;

    $result = $globFiles->use([
        'pattern' => filesystemToolsPath('docs/*.md'),
    ]);

    expect($result)->toBeArray();
    expect($result['matches'])->toContain(filesystemToolsPath('docs/one.md'));
    expect($result['matches'])->not->toContain(filesystemToolsPath('docs/two.txt'));
});

test('search in files returns matching lines', function () {
    File::ensureDirectoryExists(base_path(filesystemToolsPath('search')));
    file_put_contents(base_path(filesystemToolsPath('search/source.php')), "<?php\n\n// needle line\n");

    $searchInFiles = new SearchInFiles;

    $result = $searchInFiles->use([
        'query' => 'needle',
        'path' => filesystemToolsPath('search'),
    ]);

    expect($result)->toBeArray();
    expect($result['matches'])->toHaveCount(1);
    expect($result['matches'][0]['path'])->toBe(filesystemToolsPath('search/source.php'));
});

test('run bash script returns command output', function () {
    $runBashScript = new RunBashScript;

    $result = $runBashScript->use([
        'command' => 'php artisan --version',
    ]);

    expect($result)->toContain('Laravel Framework');
});
