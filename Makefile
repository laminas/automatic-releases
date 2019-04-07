.PHONY: *

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

all: static-analysis test no-leaks mutation-test cs ## run static analysis, tests, cs
	echo "all good"

static-analysis:
	vendor/bin/phpstan analyse
	vendor/bin/psalm

test:
	vendor/bin/phpunit

no-leaks:
	vendor/bin/roave-no-leaks

mutation-test:
	vendor/bin/infection --min-msi=58 --min-covered-msi=59

cs:
	vendor/bin/phpcs
