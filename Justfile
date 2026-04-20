IMAGE := "containers08"
CONTAINER := "container"
VOLUME := "database"

default:
    @just --list

build:
    podman build -t {{ IMAGE }} .

create:
    podman create --name {{ CONTAINER }} --volume {{ VOLUME }}:/var/www/db {{ IMAGE }}

copy-tests:
    podman cp ./tests {{ CONTAINER }}:/var/www/html

start:
    podman start {{ CONTAINER }}

test:
    podman exec {{ CONTAINER }} php /var/www/html/tests/tests.php

stop:
    -podman stop {{ CONTAINER }}

rm:
    -podman rm {{ CONTAINER }}

run-tests: build create copy-tests start test stop rm

clean: rm
    -podman rmi {{ IMAGE }}
    -podman volume rm {{ VOLUME }}
