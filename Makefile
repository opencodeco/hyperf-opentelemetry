build:
	@docker-compose build hyperf-opentelemetry

sh:
	@docker-compose run --rm hyperf-opentelemetry

install:
	@docker-compose run --rm hyperf-opentelemetry -c "composer install"

update:
	@docker-compose run --rm hyperf-opentelemetry -c "composer update"

test:
	@docker-compose run --rm hyperf-opentelemetry -c "composer test"

