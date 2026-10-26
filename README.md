# FileBot Job Handler

Symfony API that queues FileBot AMC jobs through Messenger and Redis.

## Run

Start the development stack with Docker Compose:

```sh
docker compose --profile dev up --build
```

The API is available at `http://localhost:8080`. Check it with
`GET /api/health`.

Browse the interactive Swagger UI at `http://localhost:8080/api/doc`. The
generated OpenAPI document is available at `/api/doc.json` and `/api/doc.yaml`.

## Queue a job

Send a `POST` request to `/api/jobs` with JSON such as:

```json
{
  "path": "/torrents/Example.Movie.2024",
  "label": "movie",
  "action": "test",
  "conflict": "skip",
  "mediaId": "123456"
}
```

`path` must be an absolute path visible inside the PHP and worker containers.
The API checks that it exists and contains media before creating and queuing
the job message. The worker then runs FileBot. The API
returns `202 Accepted` after the message is queued.

`action` accepts `test`, `copy`, or `move`; `conflict` independently accepts
`skip`, `replace`, `auto`, `index`, or `fail`. Use `test` to preview FileBot's
changes. `mediaId` is optional and must be numeric. With the default database
settings, use a TMDB ID for movies and a TVDB ID for TV shows.

FileBot failures are not retried automatically; submit a new job with corrected
parameters.

## Logs

FileBot output continues to go to Monolog and is also appended to
`var/log/filebot/<torrent name>.log`. Per-torrent files contain the command and
FileBot process output.

## Configuration

Defaults are declared in `config/services.yaml`; environment variables or
`.env` values override them. `TORRENTS_ROOT` and `STORAGE_ROOT` configure the
Docker volume mount paths. FileBot database and subtitle settings use the
`AMC_*` variables, and `PLEX_HOST` / `PLEX_TOKEN` are optional.

## Tests

Run the unit and integration suite with:

```sh
vendor/bin/phpunit
```
