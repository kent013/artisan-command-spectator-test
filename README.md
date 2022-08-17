# artisan-command-spectator-test
Artisan make command to generate HTTP testcases with OpenAPI and [spectator](https://github.com/hotmeteor/spectator).

## Disclaimer
This command generate only a scaffold. You need further implementation to test your API.

I'm using [api blueprint](https://apiblueprint.org/) for API specification.
Generating OpenAPI json with using [apib2swagger](https://github.com/kminami/apib2swagger). 

While I'm not writing OpenAPI directly, some data such as operation ID is not natural. Perhaps it will cause a problem on this command.

## Installation

```
composer require --dev kent013/artisan-command-spectator-test
```

## Generate config file

```
php artisan vendor:publish --tag="spectator-test"
```

## Configuration

### Default namespace

If you want to change test namespaces, please add following line in your `.env` file and change the values.

```
SPECTATORTEST_NAMESPACE=Feature
```


### OpenAPI file path
You can set default open api path with adding following line in your `.env` file and change the values.

```
SPECTATORTEST_OPENAPI_PATH=documents/api/api.openapi3.json
```

This value is able to overide with `openapi-path` option.

## Command line arguments
```
./artisan make:spectator-test TestCaseClassName APIMethodPath...
```

Where `APIMethodPath` goes like

```
/api/v1/organization/{organization_id}/projects/{project_id}
```

Will be generate all HTTP methods corresponds on the path.
If you want to select HTTP methods to generate test, prefix the path with comma-separated http methods as following.

```
GET,PUT:/api/v1/organization/{organization_id}/projects/{project_id}
```

Also you can pass multiple `APIMethodPath` to command as following.

```
GET,PUT:/api/v1/organization/{organization_id}/projects/{project_id} GET,DELETE:/api/v1/organization/{organization_id}/projects/{project_id}
```

For example,
```
./artisan make:spectator-test Http/Api/V1/OrganizationProjectTest GET,PUT:/api/v1/organization/{organization_id}
```

### Using tag

If you using tag to group API paths, then you can use `--tags` argument as following.

```
./artisan make:spectator-test:testcase --tags Http/Api/V1/ProjectTest projects 
```

## Command line options

`--openapi-path`

Path to Open API specification. You can specify Json or Yaml path or URL.

`--force`

Overrite class file or not;

`--tags`

Generate test methods matched with tags. With no `--tags` option, arguments will be processed as a path.
You cannot select HTTP methods with tags.

`--test-name-with-path`
Generate test method name from path like `testApiV1OrganizationProjectsPut204`.
By default command will use operationId like `testProjects200`

### Example Test 
```
./artisan make:spectator-test Http/Api/V1/OrganizationProjectTest GET,PUT:/api/v1/organization/{organization_id}/projects/{project_id}
```

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1;

use Spectator\Spectator;
use Tests\TestCase;

class OrganizationProjectTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Spectator::using('api.openapi3.json');
    }

    /**
     * get /api/v1/organization/{organization_id}/projects/{project_id} Get status code 200
     */
    public function testGet200(): void
    {
        $this->getJson('/api/v1/organization/1/projects/1')
            ->assertStatus(200)
            ->assertValidRequest()
            ->assertValidResponse();
    }

    /**
     * get /api/v1/organization/{organization_id}/projects/{project_id} Get status code 401
     */
    public function testGet401(): void
    {
        $this->getJson('/api/v1/organization/1/projects/1')
            ->assertStatus(401)
            ->assertValidRequest()
            ->assertValidResponse();
    }

    /**
     * get /api/v1/organization/{organization_id}/projects/{project_id} Get status code 403
     */
    public function testGet403(): void
    {
        $this->getJson('/api/v1/organization/1/projects/1')
            ->assertStatus(403)
            ->assertValidRequest()
            ->assertValidResponse();
    }

    /**
     * put /api/v1/organization/{organization_id}/projects/{project_id} Update status code 204
     */
    public function testUpdate204(): void
    {
        $requestBody = [
          'name' => 'Fofi',
          'description' => 'An Advanced Form Filler',
        ];
        $this->putJson('/api/v1/organization/1/projects/1', $requestBody)
            ->assertStatus(204)
            ->assertValidRequest()
            ->assertValidResponse();
    }

    /**
     * put /api/v1/organization/{organization_id}/projects/{project_id} Update status code 401
     */
    public function testUpdate401(): void
    {
        $requestBody = [
          'name' => 'Fofi',
          'description' => 'An Advanced Form Filler',
        ];
        $this->putJson('/api/v1/organization/1/projects/1', $requestBody)
            ->assertStatus(401)
            ->assertValidRequest()
            ->assertValidResponse();
    }

    /**
     * put /api/v1/organization/{organization_id}/projects/{project_id} Update status code 403
     */
    public function testUpdate403(): void
    {
        $requestBody = [
          'name' => 'Fofi',
          'description' => 'An Advanced Form Filler',
        ];
        $this->putJson('/api/v1/organization/1/projects/1', $requestBody)
            ->assertStatus(403)
            ->assertValidRequest()
            ->assertValidResponse();
    }

    /**
     * put /api/v1/organization/{organization_id}/projects/{project_id} Update status code 422
     */
    public function testUpdate422(): void
    {
        $requestBody = [
          'name' => 'Fofi',
          'description' => 'An Advanced Form Filler',
        ];
        $this->putJson('/api/v1/organization/1/projects/1', $requestBody)
            ->assertStatus(422)
            ->assertValidRequest()
            ->assertValidResponse();
    }
}
```