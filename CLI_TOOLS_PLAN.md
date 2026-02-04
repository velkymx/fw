# MVC Developer Tools - CLI Framework Plan

## Overview
Create a unified, developer-friendly CLI tool (`fw`) for code generation, database management, and development tasks.

## Entry Point

**File: `/fw`** (executable)
```php
#!/usr/bin/env php
<?php
define('BASE_PATH', __DIR__);
require BASE_PATH . '/vendor/autoload.php';

use Fw\Console\Application;
$app = new Application();
exit($app->run($argv));
```

## Directory Structure

```
src/Console/
├── Application.php          # CLI app bootstrap & command runner
├── Command.php              # Abstract base command class
├── Input.php                # Argument/option parser
├── Output.php               # Colorized output & formatting
├── Table.php                # Table formatting for lists
├── ProgressBar.php          # Progress indicators
├── Prompter.php             # Interactive prompts (y/n, choice, text)
└── Commands/
    ├── HelpCommand.php      # List all commands
    ├── MakeModelCommand.php
    ├── MakeControllerCommand.php
    ├── MakeMigrationCommand.php
    ├── MakeMiddlewareCommand.php
    ├── MakeProviderCommand.php
    ├── MakeCommandCommand.php    # CQRS Command
    ├── MakeQueryCommand.php      # CQRS Query
    ├── MigrateCommand.php        # Consolidates migrate.php
    ├── MigrateRollbackCommand.php
    ├── MigrateStatusCommand.php
    ├── MigrateFreshCommand.php
    ├── DbSeedCommand.php
    ├── QueueWorkCommand.php      # Consolidates worker.php
    ├── RoutesListCommand.php     # List all routes
    ├── CacheClearCommand.php
    └── ServeCommand.php          # Built-in dev server

stubs/
├── model.stub
├── controller.stub
├── controller.resource.stub     # Full CRUD
├── migration.stub
├── migration.create.stub        # With table creation
├── middleware.stub
├── provider.stub
├── command.stub                 # CQRS command
├── command.handler.stub
├── query.stub
└── query.handler.stub
```

## Core Classes

### 1. Console\Application
```php
namespace Fw\Console;

class Application {
    private array $commands = [];
    private Output $output;

    public function __construct();
    public function register(Command $command): void;
    public function run(array $argv): int;  // Returns exit code
    public function getCommands(): array;
}
```

### 2. Console\Command (Abstract Base)
```php
namespace Fw\Console;

abstract class Command {
    protected string $name;           // e.g., 'make:model'
    protected string $description;    // Short description
    protected array $arguments = [];  // ['name' => ['description', required]]
    protected array $options = [];    // ['--force' => ['description', default]]

    abstract public function handle(Input $input, Output $output): int;

    // Helpers
    protected function ask(string $question, ?string $default = null): string;
    protected function confirm(string $question, bool $default = false): bool;
    protected function choice(string $question, array $choices): string;
    protected function table(array $headers, array $rows): void;
    protected function progressStart(int $max): void;
    protected function progressAdvance(): void;
    protected function progressFinish(): void;
}
```

### 3. Console\Output
```php
namespace Fw\Console;

class Output {
    public function line(string $text): void;
    public function info(string $text): void;     // Blue
    public function success(string $text): void;  // Green
    public function warning(string $text): void;  // Yellow
    public function error(string $text): void;    // Red
    public function newLine(int $count = 1): void;
    public function table(array $headers, array $rows): void;
}
```

### 4. Console\Input
```php
namespace Fw\Console;

class Input {
    public function __construct(array $argv, Command $command);
    public function argument(string $name): ?string;
    public function option(string $name): mixed;
    public function hasOption(string $name): bool;
}
```

## Command Examples

### make:model
```
php fw make:model Post
php fw make:model Post --migration    # Also create migration
php fw make:model Post -m             # Short flag
```

**Generated:** `app/Models/Post.php`

### make:controller
```
php fw make:controller PostController
php fw make:controller PostController --resource  # Full CRUD methods
php fw make:controller Api/PostController         # Subdirectory
```

**Generated:** `app/Controllers/PostController.php`

### make:migration
```
php fw make:migration create_posts_table
php fw make:migration add_status_to_posts
```

**Generated:** `database/migrations/0005_create_posts_table.php`

### make:middleware
```
php fw make:middleware RateLimitMiddleware
```

**Generated:** `src/Middleware/RateLimitMiddleware.php`
**Updates:** `config/middleware.php` (adds alias)

### migrate
```
php fw migrate              # Run pending
php fw migrate:status       # Show status
php fw migrate:rollback     # Rollback last batch
php fw migrate:fresh        # Drop all & re-run
php fw db:seed              # Run seeders
```

### routes:list
```
php fw routes:list
```
**Output:**
```
+--------+------------------+------------------------+------------+
| Method | URI              | Action                 | Middleware |
+--------+------------------+------------------------+------------+
| GET    | /                | HomeController@index   |            |
| GET    | /posts           | PostController@index   | web        |
| POST   | /posts           | PostController@store   | web, auth  |
+--------+------------------+------------------------+------------+
```

### serve
```
php fw serve              # Start on localhost:8000
php fw serve --port=8080  # Custom port
```

## Stub Examples

### stubs/model.stub
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Fw\Model\Model;

class {{CLASS_NAME}} extends Model
{
    protected static ?string $table = '{{TABLE_NAME}}';

    protected static array $fillable = [
        //
    ];

    protected static array $casts = [
        //
    ];
}
```

### stubs/controller.resource.stub
```php
<?php

declare(strict_types=1);

namespace App\Controllers{{NAMESPACE}};

use Fw\Core\Controller;
use Fw\Core\Request;
use Fw\Core\Response;

class {{CLASS_NAME}} extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('{{VIEW_PATH}}.index');
    }

    public function create(Request $request): Response
    {
        return $this->view('{{VIEW_PATH}}.create');
    }

    public function store(Request $request): Response
    {
        // Validate and store
        return $this->redirect('/{{ROUTE_PREFIX}}');
    }

    public function show(Request $request, string $id): Response
    {
        return $this->view('{{VIEW_PATH}}.show');
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->view('{{VIEW_PATH}}.edit');
    }

    public function update(Request $request, string $id): Response
    {
        // Validate and update
        return $this->redirect('/{{ROUTE_PREFIX}}/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        // Delete
        return $this->redirect('/{{ROUTE_PREFIX}}');
    }
}
```

### stubs/middleware.stub
```php
<?php

declare(strict_types=1);

namespace Fw\Middleware;

use Fw\Core\Application;
use Fw\Core\Request;
use Fw\Core\Response;

class {{CLASS_NAME}} implements MiddlewareInterface
{
    public function __construct(
        private Application $app,
    ) {}

    public function handle(Request $request, callable $next): Response|string|array
    {
        // Before request

        $response = $next($request);

        // After request

        return $response;
    }
}
```

### stubs/migration.create.stub
```php
<?php

declare(strict_types=1);

use Fw\Database\Migration\Blueprint;
use Fw\Database\Migration\Migration;

final class {{CLASS_NAME}} extends Migration
{
    public function up(): void
    {
        $this->create('{{TABLE_NAME}}', function (Blueprint $table) {
            $table->id();
            // Add columns here
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('{{TABLE_NAME}}');
    }
}
```

### stubs/provider.stub
```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Fw\Core\ServiceProvider;

class {{CLASS_NAME}} extends ServiceProvider
{
    public function register(): void
    {
        // Register bindings in the container
    }

    public function boot(): void
    {
        // Called after all providers are registered
    }
}
```

### stubs/command.stub (CQRS)
```php
<?php

declare(strict_types=1);

namespace App\Commands;

use Fw\Bus\Command;

final readonly class {{CLASS_NAME}} implements Command
{
    public function __construct(
        // Add properties
    ) {}
}
```

### stubs/command.handler.stub
```php
<?php

declare(strict_types=1);

namespace App\Handlers;

use Fw\Bus\Handler;
use App\Commands\{{COMMAND_NAME}};

final class {{CLASS_NAME}} implements Handler
{
    public function __construct(
        // Inject dependencies
    ) {}

    public function handle({{COMMAND_NAME}} $command): mixed
    {
        // Handle the command
    }
}
```

## Implementation Order

### Phase 1: Core Framework
1. `src/Console/Output.php` - Colorized output
2. `src/Console/Input.php` - Argument parsing
3. `src/Console/Command.php` - Base command class
4. `src/Console/Application.php` - Command registry & runner
5. `/fw` - Entry point script
6. `src/Console/Commands/HelpCommand.php` - List commands

### Phase 2: Code Generators
7. Create `/stubs/` directory with all stub files
8. `MakeModelCommand.php`
9. `MakeControllerCommand.php`
10. `MakeMigrationCommand.php`
11. `MakeMiddlewareCommand.php`
12. `MakeProviderCommand.php`

### Phase 3: Database Commands
13. `MigrateCommand.php` (port from migrate.php)
14. `MigrateRollbackCommand.php`
15. `MigrateStatusCommand.php`
16. `MigrateFreshCommand.php`
17. `DbSeedCommand.php`

### Phase 4: Utility Commands
18. `QueueWorkCommand.php` (port from worker.php)
19. `RoutesListCommand.php`
20. `CacheClearCommand.php`
21. `ServeCommand.php`

### Phase 5: CQRS Generators
22. `MakeCommandCommand.php`
23. `MakeQueryCommand.php`

## Verification

1. Run `php fw` - Should show help with all commands
2. Run `php fw make:model Test` - Should create `app/Models/Test.php`
3. Run `php fw make:controller TestController --resource` - Should create with CRUD methods
4. Run `php fw make:migration create_tests_table` - Should create numbered migration
5. Run `php fw migrate:status` - Should show migration status
6. Run `php fw routes:list` - Should show all registered routes
7. Run `php fw serve` - Should start dev server

## Usage Examples

```bash
# Create a full resource
php fw make:model Post --migration
php fw make:controller PostController --resource
php fw migrate

# Create middleware
php fw make:middleware ThrottleMiddleware

# Database operations
php fw migrate:fresh --seed

# Development
php fw serve --port=8080
php fw routes:list
php fw cache:clear
```
