# SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
# SPDX-License-Identifier: MIT
.PHONY: tests

all: vendor

vendor: composer.json
	composer install

tests: vendor
	vendor/bin/phpunit tests -c tests/phpunit.xml
