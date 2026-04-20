# Лабораторная работа №8: CI для PHP-приложения в контейнере (GitHub Actions)

## Студент: Кроитор Александр

## Группа: IA2403

## Преподаватель: M. Croitor

## Дата: 20-04-2026

## Цель работы: В рамках данной работы студенты научатся настраивать непрерывную интеграцию с помощью Github Actions.

## Задание: Создать Web приложение, написать тесты для него и настроить непрерывную интеграцию с помощью Github Actions на базе контейнеров.

### Локально вместо Docker использую Podman (аналогичный API, rootless, daemonless)

Важно: в GitHub Actions на `ubuntu-latest` по умолчанию доступен Docker, поэтому workflow использует `docker ...`, а локально я делаю то же самое через `podman ...`.

```bash
 podman -v
podman version 5.7.0
```

## Структура проекта

```text
.
├── .github/workflows/main.yml
├── Dockerfile
├── Justfile
├── README.md
├── sql/schema.sql
├── site/
│   ├── config.php
│   ├── index.php
│   ├── modules/
│   │   ├── database.php
│   │   └── page.php
│   ├── styles/style.css
│   └── templates/index.tpl
└── tests/
    ├── testframework.php
    └── tests.php
```

## Выполнение работы

### 1) Приложение

Сайт реализован в `site/`:

- `site/modules/database.php` — класс `Database` для работы с SQLite (Execute/Fetch/CRUD/Count).
- `site/modules/page.php` — класс `Page` для рендера HTML по шаблону.
- `site/index.php` — читает GET-параметр `page`, достаёт запись из БД и рендерит HTML.
- `site/config.php` — хранит путь к БД (в контейнере это `/var/www/db/db.sqlite` через volume).

### 2) Схема базы

Схема и стартовые данные лежат в `sql/schema.sql` (таблица `page` + 3 записи).

### 3) Тесты

Написаны базовые юнит-тесты:

- `tests/testframework.php` — мини-фреймворк (assert + агрегирование результата).
- `tests/tests.php` — тесты для всех методов `Database` + проверки `Page::Render()` (в т.ч. escaping HTML).

### 4) Контейнер (Dockerfile)

Контейнер собирается на базе `php:7.4-fpm`, внутри ставится SQLite и расширение `pdo_sqlite`, затем из `sql/schema.sql` готовится файл базы `db.sqlite`:

```dockerfile
FROM docker.io/library/php:7.4-fpm as base

RUN apt-get update && \
    apt-get install -y sqlite3 libsqlite3-dev && \
    docker-php-ext-install pdo_sqlite

VOLUME ["/var/www/db"]

COPY sql/schema.sql /var/www/db/schema.sql

RUN echo "prepare database" && \
    cat /var/www/db/schema.sql | sqlite3 /var/www/db/db.sqlite && \
    chmod 777 /var/www/db/db.sqlite && \
    rm -rf /var/www/db/schema.sql && \
    echo "database is ready"

COPY site /var/www/html
```

### 5) Локальный запуск тестов (Podman + Justfile)

Для удобства добавлен `Justfile`, который повторяет шаги CI:

```bash
just run-tests
```

Если без `just`, то руками (логика та же, что в CI):

```bash
podman build -t containers08 .
podman create --name container --volume database:/var/www/db containers08
podman cp ./tests container:/var/www/html
podman start container
podman exec container php /var/www/html/tests/tests.php
podman stop container
podman rm container
```

## CI (GitHub Actions)

Workflow лежит в `.github/workflows/main.yml` и делает следующее: checkout → build → create container + volume → копирует тесты → стартует контейнер → запускает тесты → чистит контейнер.

```yml
name: CI

on:
  push:
    branches:
      - main

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4
      - name: Build the Docker image
        run: docker build -t containers08 .
      - name: Create `container`
        run: docker create --name container --volume database:/var/www/db containers08
      - name: Copy tests to the container
        run: docker cp ./tests container:/var/www/html
      - name: Up the container
        run: docker start container
      - name: Run tests
        run: docker exec container php /var/www/html/tests/tests.php
      - name: Stop the container
        run: docker stop container
      - name: Remove the container
        run: docker rm container
```

## QA time

Q: Что такое непрерывная интеграция (CI)?
A: Это практика, когда при каждом изменении кода (push/PR) автоматически запускаются проверки (сборка, тесты, линтеры). Цель — быстро ловить ошибки и не копить проблемы до конца разработки.

Q: Для чего нужны юнит-тесты? Как часто их нужно запускать?
A: Юнит-тесты проверяют отдельные части кода (функции/классы) в изоляции и помогают убедиться, что изменения не сломали существующее поведение. Оптимально запускать их при каждом изменении: локально перед коммитом и автоматически в CI на каждый push/PR.

Q: Что нужно изменить в `.github/workflows/main.yml`, чтобы тесты запускались при каждом Pull Request?
A: Добавить триггер `pull_request` (обычно в main):

```yml
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]
```

Q: Что добавить в `.github/workflows/main.yml`, чтобы удалять созданные образы после выполнения тестов?
A: В конец job добавить шаг удаления образа (и при необходимости volume). Чтобы очистка выполнялась даже при падении тестов, лучше добавить `if: always()`:

```yml
- name: Remove image
  if: always()
  run: docker rmi -f containers08
```

## Выводы

В ходе работы создано PHP-приложение с SQLite, написаны юнит-тесты и настроен CI в GitHub Actions для автоматической сборки контейнера и запуска тестов внутри него.
