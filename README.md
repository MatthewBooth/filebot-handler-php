# FileBot Job Handler

Symfony API that queues FileBot AMC jobs through Messenger and Redis.

## Run

Start the development stack with Docker Compose:

```sh
docker compose --profile dev up --build
```

The API is available at `http://localhost:8080`. Check it with
`GET /api/v1/health`.

## Queue a job

Send a `POST` request to `/api/v1/jobs` with JSON such as:

```json
{
  "paths": ["/torrents/Example.Movie.2024"],
  "label": "movie",
  "action": "test",
  "conflict": "skip",
  "mediaId": "123456"
}
```

Paths must be absolute paths visible inside the PHP and worker containers. The
API validates and queues the request, then the worker runs FileBot. The API
returns `202 Accepted` after the message is queued.

`action` accepts `test`, `copy`, or `move`; `conflict` independently accepts
`skip`, `replace`, `auto`, `index`, or `fail`. Use `test` to preview FileBot's
changes. `mediaId` is optional and must be numeric. With the default database
settings, use a TMDB ID for movies and a TVDB ID for TV shows.

Jobs that match no media are logged as `no_match` and acknowledged without a
Messenger retry. Other FileBot failures are retried by Messenger.

## Logs

FileBot output continues to go to Monolog and is also appended to
`var/log/filebot/<torrent name>.log`. Per-torrent files include the job options,
input paths, FileBot output, and final result.

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
