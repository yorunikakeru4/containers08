IMAGE := "containers10-backend"

default:
    @just --list

build:
    podman-compose build

up:
    podman-compose up -d

down:
    podman-compose down

logs:
    podman-compose logs -f

restart: down up

clean: down
    -podman rmi {{ IMAGE }}

scout:
    docker scout quickview {{ IMAGE }}
