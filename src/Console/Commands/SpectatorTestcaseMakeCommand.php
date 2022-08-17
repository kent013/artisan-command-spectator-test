<?php

declare(strict_types=1);

namespace ArtisanCommandSpectatorTest\Console\Commands;

use cebe\openapi\exceptions\IOException;
use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\exceptions\UnresolvableReferenceException;
use cebe\openapi\json\InvalidJsonPointerSyntaxException;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Responses;
use Illuminate\Console\GeneratorCommand as Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException as AssertInvalidArgumentException;

class SpectatorTestcaseMakeCommand extends Command
{
    /**
     * @var OpenApi
     */
    protected OpenApi $openapi;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:spectator-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new spectator testcase class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'TestCase';

    /**
     * @return bool|void|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $this->initializeOpenAPI();

        if (parent::handle() === false && !$this->option('force')) {
            return;
        }
    }

    /**
     * Get the destination class path.
     *
     * @param string $name
     * @return string
     */
    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return base_path('tests') . str_replace('\\', '/', $name) . '.php';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\\' . config('spectator-test.namespace');
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace()
    {
        return 'Tests';
    }

    /**
     * initialize open API instance
     *
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws TypeErrorException
     * @throws UnresolvableReferenceException
     * @throws IOException
     * @throws InvalidJsonPointerSyntaxException
     */
    protected function initializeOpenAPI(): void
    {
        $path = $this->getOpenAPIPath();

        $extension = File::extension($path);

        if (in_array($extension, ['yml', 'yaml'])) {
            $this->openapi = Reader::readFromYamlFile($path);
        } elseif ($extension === 'json') {
            $this->openapi = Reader::readFromJsonFile($path);
        } else {
            $this->error('OpenAPI file is not found, You can specify json/yaml local file path or URL.');
            exit;
        }

        $this->info("OpenAPI definition loaded from {$path}.");
    }

    /**
     * @return string
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    protected function getOpenAPIPath(): string
    {
        $path = null;

        if ($this->option('openapi-path')) {
            $path = $this->option('openapi-path');
        } elseif (!is_null(config('spectator-test.openapi_path', null))) {
            $path = config('spectator-test.openapi_path');
        } else {
            $this->error('Please set openapi path. You can specify json/yaml local file path or URL. (ENV value is SPECTATOR_TEST.OPENAPI_PATH, or --openapi-path option');
            exit;
        }

        if (!is_string($path)) {
            $this->error('OpenAPI file is not found, You can specify json/yaml local file path or URL.');
            exit;
        }
        $path = realpath($path);

        if (!is_string($path)) {
            $this->error('OpenAPI file is not found, You can specify json/yaml local file path or URL.');
            exit;
        }
        return $path;
    }

    /**
     * @return array<array<int, string>>
     */
    protected function getArguments()
    {
        return array_merge(
            parent::getArguments(),
            [
                ['api_paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'the api path'],
            ]
        );
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, int|string|null>>
     */
    protected function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                ['openapi-path', null, InputOption::VALUE_OPTIONAL, 'The api path'],
                ['force', null, InputOption::VALUE_NONE, 'Create the class even if the UseCase already exists'],
                ['append', null, InputOption::VALUE_NONE, 'Append test function into TestCase class if already exists'],
                ['tags', null, InputOption::VALUE_NONE, 'Argument as tags'],
                ['test-name-with-path', null, InputOption::VALUE_NONE, 'Generate method name from operation id'],
            ]
        );
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/../../../stubs/testcase.stub';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getTestFunctionStub()
    {
        return __DIR__ . '/../../../stubs/test_method.stub';
    }

    /**
     * Class Generator function
     *
     * @param string $name name of class
     * @return string generated class string
     * @throws InvalidArgumentException
     * @throws AssertInvalidArgumentException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws FileNotFoundException
     */
    protected function buildClass($name)
    {
        $paths = $this->argument('api_paths');

        Assert::isIterable($paths);

        $operations = [];

        // if tags option is specified treat api_paths as tags
        if ($this->option('tags')) {
            $tags = array_map('strtolower', $paths);
            $operations = $this->extractOperationsFromTags($tags);
        } else {
            $operations = $this->extractOperationsFromPaths($paths);
        }

        if (empty($operations)) {
            $this->error('No API definiton matches with specified argument.');
            exit;
        }

        $functions = [];

        foreach ($operations as $operation) {
            $functions[] = $this->buildTestFunctions($operation);
        }

        $replace = ['DummyTests' => implode("\n\n", $functions),
            'DummyOpenAPIPath' => basename($this->getOpenAPIPath()), ];
        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Extract openAPI operations from tags
     *
     * @param array<int, string> $tags
     * @return array <string, SpectatorTestcaseMakeCommandApiOperation>
     * @throws InvalidArgumentException
     * @throws AssertInvalidArgumentException
     */
    protected function extractOperationsFromTags(array $tags): array
    {
        $results = [];

        foreach ($tags as $tag) {
            $operations = $this->searchOperationsByTag($tag);
            $results = array_merge($results, $operations);
        }
        return $results;
    }

    /**
     * Seach openAPI operations from tag
     *
     * @param string $tag
     * @return array <string, SpectatorTestcaseMakeCommandApiOperation>
     * @throws InvalidArgumentException
     * @throws AssertInvalidArgumentException
     */
    protected function searchOperationsByTag(string $tag): array
    {
        $results = [];

        foreach ($this->openapi->paths->getPaths() as $path => $item) {
            foreach ($item->getOperations() as $method => $operation) {
                if (!in_array($tag, array_map('strtolower', $operation->tags))) {
                    continue;
                }
                $results[$path . $method] = new SpectatorTestcaseMakeCommandApiOperation($path, $method, $item, $operation);
            }
        }
        return $results;
    }

    /**
     * Extract openAPI operations from apipath string
     *
     * ex)
     * /api/v1/user
     * GET:/api/v1/user
     * GET:/api/v1/user POST,DELETE,OPTION:/api/v1/project
     *
     * @param array<int, string> $paths
     * @return array <string, SpectatorTestcaseMakeCommandApiOperation>
     * @throws InvalidArgumentException
     * @throws AssertInvalidArgumentException
     */
    protected function extractOperationsFromPaths(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            if (!preg_match('/^(([a-z,]+):)?(.+)$/i', $path, $regs)) {
                $this->warn("Specified path {$path} is invalid format. Acceptable format is API_PATH or COMMA_SEPARATED_METHODS:API_PATH (ex: GET,POST:/api/v1/user)");
            }

            $apipath = $regs[3];

            if (!$this->openapi->paths->hasPath($apipath)) {
                $this->warn("Specified path {$path} not found. Skip.");
                continue;
            }
            $methods = [];

            if (!empty($regs[2])) {
                $methods = explode(',', mb_strtolower($regs[2]));
            }
            $operations = $this->extractOperation($apipath, $methods);
            $results = array_merge($results, $operations);
        }
        return $results;
    }

    /**
     * build test functions
     *
     * @param string $path
     * @param array<int, string> $methods
     * @return array<string, SpectatorTestcaseMakeCommandApiOperation>
     */
    protected function extractOperation(string $path, array $methods): array
    {
        $item = $this->openapi->paths->getPath($path);
        Assert::notNull($item);
        $operations = $item->getOperations();
        $filtered = [];

        // if no methods specified, then use all operations
        if (empty($methods)) {
            $methods = array_keys($operations);
        }

        foreach ($methods as $method) {
            if (!isset($operations[$method])) {
                $this->warn("Method {$method} not found on {$path}. Skip.");
                continue;
            }
            $filtered[$path . $method] = new SpectatorTestcaseMakeCommandApiOperation($path, $method, $item, $operations[$method]);
        }
        return $filtered;
    }

    /**
     * @param SpectatorTestcaseMakeCommandApiOperation $specification
     * @return string
     */
    protected function buildTestFunctions($specification): string | null
    {
        $response = $specification->operation->responses;
        Assert::isInstanceOf($response, Responses::class);
        $possibleStatusCodes = array_keys($response->getResponses());
        $functions = [];

        foreach ($possibleStatusCodes as $statusCode) {
            $functions[] = $this->buildTestFunction($specification, (string) $statusCode);
        }
        return implode("\n\n", $functions);
    }

    /**
     * build test function with status code
     *
     * @param SpectatorTestcaseMakeCommandApiOperation $specification
     * @param string $statusCode
     * @return string
     */
    protected function buildTestFunction($specification, string $statusCode): string | null
    {
        $replacement = [];

        $replacement['DummyDocument'] = "{$specification->method} {$specification->path} {$specification->operation->summary} status code {$statusCode}";

        $replacement['DummyTestFunctionName'] = $this->generateTestMethodName($specification, $statusCode);

        $replacement['DummyTestMethod'] = strtolower($specification->method) . 'Json';

        $exampleParameters = [];

        foreach ($specification->operation->parameters as $parameter) {
            Assert::isInstanceOf($parameter, Parameter::class);
            $exampleParameters[$parameter->name] = $parameter->example;
        }

        $replacement['DummyEndPoint'] = preg_replace_callback(
            '/\{(.+?)\}/',
            function (array $matches) use ($exampleParameters): string {
                $replaced = $exampleParameters[$matches[1]];
                Assert::string($replaced);
                return $replaced;
            },
            $specification->path
        );

        $replacement['DummyRequestBody'] = '';
        $replacement['DummyRequestParameter'] = '';

        if (!is_null($requestBody = $specification->operation->requestBody)) {
            Assert::isInstanceOf($requestBody, RequestBody::class);
            $exampleRequestBody = $requestBody->content['application/json']->example;
            $exampleRequestBody = $this->exportRequestBody($exampleRequestBody);

            $replacement['DummyRequestBody'] = $exampleRequestBody;
            $replacement['DummyRequestParameter'] = ', $requestBody';
        }
        $replacement['DummyAssertFunctions'] = "->assertStatus({$statusCode})";

        $template = file_get_contents($this->getTestFunctionStub());
        Assert::string($template);
        return str_replace(array_keys($replacement), array_values($replacement), $template);
    }

    /**
     * PHP var_export() with short array syntax (square brackets) indented 2 spaces.
     *
     * NOTE: The only issue is when a string value has `=>\n[`, it will get converted to `=> [`
     * @link https://www.php.net/manual/en/function.var-export.php
     * @param mixed $expression
     * @return string exported value
     */
    protected function exportRequestBody(mixed $expression): string
    {
        $export = var_export($expression, true);
        $patterns = [
            '/array \\(/' => '[',
            '/^([ ]*)\\)(,?)$/m' => '$1]$2',
            "/=>[ ]?\n[ ]+\\[/" => '=> [',
            "/([ ]*)(\\'[^\\']+\\') => ([\\[\\'])/" => '$1$2 => $3',
        ];
        $result = preg_replace(array_keys($patterns), array_values($patterns), $export);
        Assert::string($result);
        $result = $this->indentString($result, 2);
        $result = '$requestBody = ' . preg_replace('/^ +/', '', $result) . ";\n        ";
        Assert::string($result);
        return $result;
    }

    /**
     * indent code
     *
     * @param string $string to indent
     * @param int $depth depth to indent tab size is 4
     * @return string
     */
    protected function indentString(string $string, int $depth): string
    {
        $lines = explode("\n", $string);
        $result = [];

        foreach ($lines as $line) {
            $result[] = str_repeat(' ', 4 * $depth) . $line;
        }
        return implode("\n", $result);
    }

    /**
     * generate test method name
     *
     * @param SpectatorTestcaseMakeCommandApiOperation $specification
     * @param string $statusCode
     * @return string
     */
    protected function generateTestMethodName(SpectatorTestcaseMakeCommandApiOperation $specification, string $statusCode): string
    {
        $prefix = 'test';

        if ($this->option('test-name-with-path')) {
            // generate method name from path, method and status code
            // remove place holder
            $path = preg_replace('/\/\{(.+?)\}/', '', $specification->path);
            $path = $prefix . '/' . $path . '/' . $specification->method . $statusCode;
            return Str::camel(str_replace('/', '_', $path));
        }

        // otherwise, generate method name from operationId
        return $prefix . Str::ucfirst(Str::camel($specification->operation->operationId)) . $statusCode;
    }
}

/**
 * data structure
 */
class SpectatorTestcaseMakeCommandApiOperation
{
    /**
     * @param string $path
     * @param string $method
     * @param \cebe\openapi\spec\PathItem $pathItem
     * @param \cebe\openapi\spec\Operation $operation
     */
    public function __construct(public $path, public $method, public $pathItem, public $operation)
    {
    }
}
