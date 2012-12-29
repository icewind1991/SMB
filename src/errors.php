<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace SMB;

class NotFoundException extends \Exception {
}

class AlreadyExistsException extends \Exception {
}

class NotEmptyException extends \Exception {
}

class ConnectionError extends \Exception {
}

class AccessDeniedException extends \Exception {
}
