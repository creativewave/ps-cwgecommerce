
SHELL = /bin/sh

dir := $(notdir $(CURDIR))
dist_files := $(wildcard classes controllers css/*.* js/*.* LICENSE translations views *.php *.png)

.PHONY: help test lint publish

help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
	| awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-15s\033[0m %s\n", $$1, $$2}' \
	| sed -e 's/\[32m##/[33m/'

test: ## Run tests.
	#composer validate --strict
	find . classes -maxdepth 1 -name \*.php -type f -exec php -l '{}' \;
	vendor/bin/phpunit --log-junit phpunit/junit.xml tests

lint: ## Detect coding style errors (pass FIX=1 to fix them).
	vendor/bin/php-cs-fixer fix --stop-on-violation $(if $(FIX),-n -q,-v --dry-run --diff)

publish: $(dir).zip $(dir).tar.gz ## Publish new release on Github.
	token=`cat .github-token` \
	&& version=`git describe` \
	&& upload_url=`curl \
		-H "Authorization: token $$token" \
		-d '{"tag_name": "'$$version'"}' \
		https://api.github.com/repos/creativewave/ps-$(dir)/releases \
		| grep -Po 'upload_url": "\K[^{]+'` \
	&& curl --data-binary @$< \
		-H "Authorization: token $$token" \
		-H 'Content-Type: application/octet-stream' \
		"$$upload_url?name=$<&label=$<" \
	&& curl --data-binary @$(filter-out $<,$^) \
		-H "Authorization: token $$token" \
		-H 'Content-Type: application/octet-stream' \
		"$$upload_url?name=$(filter-out $<,$^)&label=$(filter-out $<,$^)" \
	&& rm -f $+

$(dir).zip: $(dist_files)
	zip -r $@ $+

$(dir).tar.gz: $(dist_files)
	tar -zcf $@ $+
