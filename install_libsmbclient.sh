#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2015 Robin Appelman <robin@icewind.nl>
# SPDX-License-Identifier: MIT
#
wget -O /tmp/libsmbclient-php.zip https://github.com/eduardok/libsmbclient-php/archive/master.zip
unzip /tmp/libsmbclient-php.zip -d /tmp
cd /tmp/libsmbclient-php-master
phpize && ./configure && make && sudo make install
echo 'extension="smbclient.so"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
