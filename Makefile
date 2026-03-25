.PHONY: all

UID=$(shell id -u)
ARGS=USER_UID=${UID}
SAIL=${ARGS} ./vendor/bin/sail
PINT=${ARGS} ./vendor/bin/pint

######################
#	INSTALLATION
######################

build:
	docker run --rm \
        -u "$(shell id -u):$(shell id -g)" \
        -v $(shell pwd):/var/www/html \
		-w /var/www/html \
		laravelsail/php84-composer:latest \
		composer install --ignore-platform-reqs

install-backend:
	@read -p "Enter project name: " APP_NAME; \
	sed -i "s|^APP_NAME=.*|APP_NAME=$${APP_NAME}|" .env; \
	sed -i "s|^APP_URL=.*|APP_URL=http://$${APP_NAME}.local|" .env; \
	echo "Updated APP_NAME and APP_URL in .env ($${APP_NAME})"; \
	if grep -q "$${APP_NAME}.local" /etc/hosts; then \
		echo "Virtual host $${APP_NAME} exists"; \
	else echo "127.0.0.1	$${APP_NAME}.local" | sudo tee -a /etc/hosts; \
	fi
	${SAIL} up -d
	${SAIL} composer install
	${SAIL} artisan key:generate -n
	${SAIL} yarn install --non-interactive
	${SAIL} yarn run --non-interactive build
	${SAIL} artisan migrate:fresh --seed

install-front:
	${SAIL} yarn install --non-interactive
	${SAIL} yarn run --non-interactive build

install: install-backend install-front

######################
#	DOCKER
######################

up:
	${SAIL} up -d

down:
	${SAIL} stop

bash:
	${SAIL} bash

######################
#	TOOLS
######################

phpstan:
	${SAIL} php ./vendor/bin/phpstan analyse --memory-limit=2G

pint:
	${SAIL} php ./vendor/bin/pint

######################
#	ARTISAN
######################

test:
	${SAIL} php artisan test

fresh:
	${SAIL} php artisan migrate:fresh --seed

ide:
	${SAIL} php artisan ide-helper:model -M

######################
#	ALIASES
######################

all-lint: pint phpstan

all-checks: all-lint test

pre-commit: fresh ide all-checks
