# Time table plugin

A plugin for registrering time in Leantime.

## Development

Clone this repository into your Leantime plugins folder:

``` shell
git clone https://github.com/ITK-Leantime/leantime-timetable.git app/Plugins/TimeTable
```

Install the plugin through Leantime in your web browser.

The install process symlinks the built file dist/timeTable.js with public/dist/js/timeTable.js in leantime.

Run this to watch files

``` shell name=development-npm-install
docker compose run --rm php npm install
docker compose run --rm php npm run dev
```

Run composer install

```shell name=development-install
docker run --interactive --rm --volume ${PWD}:/app itkdev/php8.3-fpm:latest composer install
```

### Composer normalize

```shell name=composer-normalize
docker run --rm --volume ${PWD}:/app itkdev/php8.3-fpm:latest composer normalize
```

### Coding standards

#### Check and apply with phpcs

```shell name=check-coding-standards
docker run --interactive --rm --volume ${PWD}:/app itkdev/php8.3-fpm:latest composer coding-standards-check
```

```shell name=apply-coding-standards
docker run --interactive --rm --volume ${PWD}:/app itkdev/php8.3-fpm:latest composer coding-standards-apply
```

#### Check and apply with prettier

```shell name=prettier-check
docker run --rm -v "$(pwd):/work" tmknom/prettier:latest --check assets
```

```shell name=prettier-apply
docker run --rm -v "$(pwd):/work" tmknom/prettier:latest --write assets
```

#### Check and apply markdownlint

```shell name=markdown-check
docker run --rm --volume "$PWD:/md" itkdev/markdownlint '**/*.md'
```

```shell name=markdown-apply
docker run --rm --volume "$PWD:/md" itkdev/markdownlint '**/*.md' --fix
```

#### Blade lint

```shell name=blade-apply
docker run --rm --volume "$PWD:/app" -w /app shufo/blade-formatter:1.11.11 Templates/*.blade.php --write
```

```shell name=blade-check
docker run --rm --volume "$PWD:/app" -w /app shufo/blade-formatter:1.11.11 Templates/*.blade.php --check-formatted
```

#### Check with shellcheck

```shell name=shell-check
docker run --rm --volume "$PWD:/app" --workdir /app peterdavehello/shellcheck shellcheck bin/create-release
docker run --rm --volume "$PWD:/app" --workdir /app peterdavehello/shellcheck shellcheck bin/deploy
docker run --rm --volume "$PWD:/app" --workdir /app peterdavehello/shellcheck shellcheck bin/local.create-release
```

### Code analysis

```shell name=code-analysis
# This analysis takes a bit more than the default allocated ram.
docker run --interactive --rm --volume ${PWD}:/app --env PHP_MEMORY_LIMIT=256M itkdev/php8.3-fpm:latest composer code-analysis
```

## Test release build

```shell name=test-create-release
docker compose build && docker compose run --rm php bin/create-release dev-test
```

The create-release script replaces `@@VERSION@@` in
[register.php](https://github.com/ITK-Leantime/leantime-timetable/blob/develop/register.php#L13) and
[Services/TimeTable.php](https://github.com/ITK-Leantime/leantime-timetable/blob/develop/Services/TimeTable.php#L12)
with the tag provided (in the above it is `dev-test`).

## Deploy

The deploy script downloads a [release](https://github.com/ITK-Leantime/leantime-timetable/releases) from Github and
unzips it. The script should be passed a tag as argument. In the process the script deletes itself, but the script
finishes because it [is still in memory](https://linux.die.net/man/3/unlink).
