# Laravel & PHP Guidelines for Cursor AI

## Core Principle
Follow Laravel conventions first. If Laravel has a documented way to do something, use it. Only deviate when you have a clear justification.

## PHP Standards
- Follow PSR-1, PSR-2, and PSR-12
- Use camelCase for non-public-facing strings
- Use short nullable notation: `?string` not `string|null`
- Always specify `void` return types when methods return nothing
- Use typed properties, not docblocks
- Use constructor property promotion when all properties can be promoted
- One trait per line

## Type Declarations & Docblocks
- Use typed properties over docblocks
- Specify return types including `void`
- Use short nullable syntax: `?Type` not `Type|null`
- Always import classnames in docblocks - never use fully qualified names
- Use one-line docblocks when possible: `/** @var string */`
- For iterables, always specify key and value types: `@param array<int, MyObject> $myArray`
- Use array shape notation for fixed keys, put each key on its own line:
  ```php
  /** @return array{
     first: SomeClass,
     second: SomeClass
  } */
  ```

## Control Flow
- Happy path last: Handle error conditions first, success case last
- Avoid else: Use early returns instead of nested conditions
- Separate conditions: Prefer multiple if statements over compound conditions
- Always use curly brackets even for single statements
- Ternary operators: Each part on own line unless very short

## Laravel Conventions

### Controllers
- Use singular resource names (BookController, UserController)
- Stick to CRUD methods (index, create, store, show, edit, update, destroy)
- Extract new controllers for non-CRUD actions

### Routes
- URLs: kebab-case (/open-source)
- Route names: camelCase (->name('openSource'))
- Parameters: camelCase ({userId})
- Use tuple notation: [Controller::class, 'method']

### Configuration
- Files: kebab-case (pdf-generator.php)
- Keys: snake_case (chrome_path)
- Add service configs to config/services.php, don't create new files
- Use config() helper, avoid env() outside config files

### Artisan Commands
- Names: kebab-case (delete-old-records)
- Always provide feedback ($this->comment('All ok!'))
- Show progress for loops, summary at end
- Put output BEFORE processing item

## Strings & Formatting
- Use string interpolation over concatenation
- Use __() function over @lang for translations

## Enums
- Use PascalCase for enum values

## Comments
- Avoid comments - write expressive code instead
- Single line: // with space after
- Multi-line: /* start with single *
- Refactor comments into descriptive function names

## Whitespace
- Add blank lines between statements for readability
- Exception: sequences of equivalent single-line operations
- No extra empty lines between {} brackets
- Let code "breathe" - avoid cramped formatting

## Validation
- Use array notation for multiple rules: ['email' => ['required', 'email']]
- Custom validation rules use snake_case

## Blade Templates
- Indent with 4 spaces
- No spaces after control structures: @if($condition)

## Authorization
- Policies use camelCase: Gate::define('editPost', ...)
- Use CRUD words, but 'view' instead of 'show'

## API Routing
- Use plural resource names: /books
- Use kebab-case: /error-occurrences
- Limit deep nesting for simplicity

## Naming Conventions Quick Reference
- Classes: PascalCase (UserController, OrderStatus)
- Methods/Variables: camelCase (getUserName, $firstName)
- Routes: kebab-case (/open-source, /user-profile)
- Config files: kebab-case (pdf-generator.php)
- Config keys: snake_case (chrome_path)
- Artisan commands: kebab-case (delete-old-records)
- Controllers: singular resource name + Controller (BookController, UserController)
- Views: camelCase (openSource.blade.php)
- Jobs: action-based (CreateUser, SendEmailNotification)
- Events: tense-based (UserRegistering, UserRegistered)
- Listeners: action + Listener suffix (SendInvitationMailListener)
- Commands: action + Command suffix (PublishScheduledPostsCommand)
- Mailables: purpose + Mail suffix (AccountActivatedMail)
- Resources/Transformers: plural + Resource/Transformer (UsersResource)
- Enums: descriptive name, no prefix (OrderStatus, BookingType)

## Code Quality Reminders
- Use typed properties over docblocks
- Prefer early returns over nested if/else
- Use constructor property promotion when all properties can be promoted
- Avoid else statements when possible
- Use string interpolation over concatenation
- Always use curly braces for control structures
