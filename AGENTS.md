# AGENTS.md

This is a Symfony API project for queueing and preparing FileBot jobs.

Check `composer.json` and `composer.lock` for the exact PHP, Symfony, PHPUnit,
and package versions before making framework-specific changes. This project is
currently developed around PHP 8.4 and Symfony 8.1 components.

Do not assume extra infrastructure is available unless it exists in the project.
This app currently uses Symfony Messenger, Serializer, Validator, Monolog, and
Redis Messenger transport.

## Project shape

Important application areas:

- `src/Controller/Api/` contains HTTP API controllers.
- `src/Dto/` contains request DTOs validated through Symfony Validator.
- `src/Model/` contains immutable application/domain data models.
- `src/Message/` contains Messenger messages.
- `src/MessageHandler/` contains Messenger handlers.
- `src/Service/` contains application services such as FileBot command building.
- `src/Config/` contains configuration value objects wired from environment variables.
- `tests/` contains PHPUnit tests.

The main FileBot job flow is:

1. API request hits `FilebotJobController`.
2. Symfony maps JSON into `CreateFilebotJobRequestDto` with `#[MapRequestPayload]`.
3. Validator constraints validate the request DTO.
4. DTO converts to `FilebotJobModel`.
5. Controller dispatches `FilebotJobMessage` through Messenger.
6. `FilebotJobMessageHandler` receives the message and delegates to `FilebotRunnerInterface`.
7. `FilebotRunnerService` builds/logs FileBot AMC commands from `FilebotJobModel` and `FilebotConfig`.

Keep that object-based flow intact. Do not regress service APIs back to loose arrays
or positional primitive arguments.

## Ask before generating

If the task does not specify, ask rather than guess:

- Persistence: Doctrine ORM, Doctrine ODM, or none?
- Interface: API only, server-rendered Twig, or both?
- Auth: SecurityBundle, and which authenticator?
- Queue behavior: synchronous in-process behavior, Messenger async transport, or both?
- FileBot behavior: should commands only be logged, executed, or dispatched to another process?

If you cannot ask, state the assumption and choose the smallest option that fits
the existing project.

## Symfony conventions

Follow Symfony best practices:

- Use PHP attributes for framework metadata:
    - `#[Route]`
    - `#[MapRequestPayload]`
    - `#[Assert\...]`
    - `#[AsMessageHandler]`
    - `#[Autowire]` for env/config injection where needed
- Prefer constructor injection, autowiring, and autoconfiguration.
- Keep controllers thin. Controllers should validate/map request data and delegate
  business work to services or Messenger.
- Use DTOs for API request payloads.
- Use immutable models/value objects where practical.
- Prefer `readonly` for DTOs and models.
- Use Symfony Validator constraints instead of manual validation in controllers.
- Use Messenger for async work instead of manually invoking long-running processes
  from controllers.

## Validator gotchas

This project uses a recent Symfony Validator version. Use named arguments for
constraints that accept option arrays.

Correct:
