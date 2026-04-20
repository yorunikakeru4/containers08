# Лабораторная работа №10: Управление секретами в контейнерах

## Студент: Кроитор Александр

## Группа: IA2403

## Преподаватель: M. Croitor

## Дата: 20-04-2026

## Цель работы: Целью работы является знакомство с методами управления секретами в контейнерах.

## Задание: Создать многосервисное приложение с контейнерами, использующими секреты.

### Локально вместо Docker использую Podman (аналогичный API, rootless, daemonless)

```bash
podman -v
podman version 5.7.0
```

За основу берём лабораторную работу номер 8, создаём docker-compose.yaml

```yaml
services:
  frontend:
    image: nginx:latest
    ports:
      - "80:80"
    volumes:
      - ./site:/var/www/html
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - frontend
  backend:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      MYSQL_HOST: database
      MYSQL_DATABASE: my_database
    secrets:
      - user
      - secret
    networks:
      - backend
      - frontend
  database:
    image: mariadb:latest
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/root_secret
      MYSQL_DATABASE: my_database
      MYSQL_USER_FILE: /run/secrets/user
      MYSQL_PASSWORD_FILE: /run/secrets/secret
    secrets:
      - root_secret
      - user
      - secret
    volumes:
      - ./sql/schema.sql:/docker-entrypoint-initdb.d/schema.sql
    networks:
      - backend
      - frontend

networks:
  frontend: {}
  backend: {}

secrets:
  root_secret:
    file: ./secrets/root_secret
  user:
    file: ./secrets/user
  secret:
    file: ./secrets/secret
```

Изменяем обёртку над Базой данных для работы с MySQL

Меняем Dockerfile на использование pdo_mysql

```dockerfile
FROM php:7.4-fpm AS base

# install pdo_mysql extension
RUN apt-get update && \
    apt-get install -y libzip-dev && \
    docker-php-ext-install pdo_mysql

# copy site files
COPY site /var/www/html
```

Берём конфигурационный файл для nginx из лабораторной номер 7

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html;
    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    location ~ \.php$ {
        fastcgi_pass backend:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Создаём папку secrets

```bash
mkdir secrets && cd secrets && touch root_secret user secret
```

Прописываем содержимое файлов:

- `root_secret` - пароль суперпользователя
- `user` - имя пользователя базы данных
- `secret` - пароль пользователя базы данных

Теперь самое интересное - защита секретов! Вместо передачи паролей через переменные окружения, читаем их из файлов в `/run/secrets/`

Изменяем config.php:

```php
<?php

$config = [
    "db" => [
        "host" => getenv("MYSQL_HOST") ?: "localhost",
        "database" => getenv("MYSQL_DATABASE") ?: "my_database",
        "username" => trim(file_get_contents("/run/secrets/user")),
        "password" => trim(file_get_contents("/run/secrets/secret")),
    ],
];
```

Запускаем всё это дело

```bash
podman-compose up -d
```

Проверяем что контейнеры работают

```bash
podman-compose ps
```

Проверяем образ на безопасность

```bash
docker scout quickview containers10-backend
```

QA time

Q: Почему плохо передавать секреты в образ при сборке?

A: Секреты сохраняются в слоях образа навсегда. Даже если удалить файл в следующем слое, предыдущий слой всё ещё содержит секрет. Любой с доступом к образу может вытащить секреты через `docker history` или распаковку слоёв. При пуше в реестр секреты становятся публичными.

Q: Как можно безопасно управлять секретами в контейнерах?

A: Docker/Podman Secrets - встроенный механизм, монтирует секреты в `/run/secrets/`. Внешние системы типа HashiCorp Vault, AWS Secrets Manager. Переменные `*_FILE` которые читают значение из файла вместо прямой передачи. Kubernetes Secrets для k8s окружений.

Q: Как использовать Docker Secrets для управления конфиденциальной информацией?

A: Определяем секреты в секции `secrets` docker-compose.yaml, указывая путь к файлам. Подключаем к сервисам через `secrets: [secret_name]`. В контейнере читаем из `/run/secrets/secret_name`. Для баз данных используем переменные `MYSQL_PASSWORD_FILE` вместо `MYSQL_PASSWORD`. Секреты монтируются в tmpfs и не попадают в образ.

Выводы: Docker Secrets позволяет безопасно передавать конфиденциальные данные в контейнеры без их сохранения в образах или переменных окружения. Секреты монтируются как файлы в `/run/secrets/` и доступны только внутри контейнера во время выполнения, что значительно повышает безопасность приложения.
