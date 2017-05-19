<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

use Icewind\SMB\Exception\AuthenticationException;
use Icewind\SMB\Exception\InvalidHostException;

class Server {

    const LOCALE = 'en_US.UTF-8';

    /**
     * @var string $host
     */
    protected $host;

    /**
     * @var string $user
     */
    protected $user;

    /**
     * @var string $password
     */
    protected $password;

    /**
     * @var string $workgroup
     */
    protected $workgroup;

    /**
     * @var \Icewind\SMB\System
     */
    private $system;

    /**
     * @var TimeZoneProvider
     */
    private $timezoneProvider;
    
    /**
     *
     * @var string $maxProtocol 
     */
    private $maxProtocol;

    /**
     * Check if the smbclient php extension is available
     *
     * @return bool
     */
    public static function NativeAvailable() {
        return function_exists('smbclient_state_new');
    }

    /**
     * @param string $host
     * @param string $user
     * @param string $password
     */
    public function __construct($host, $user, $password) {
        $this->host = $host;
        list($workgroup, $user) = $this->splitUser($user);
        $this->user = $user;
        $this->workgroup = $workgroup;
        $this->password = $password;
        $this->system = new System();
        $this->timezoneProvider = new TimeZoneProvider($host, $this->system);
    }

    /**
     * Split workgroup from username
     *
     * @param $user
     * @return string[] [$workgroup, $user]
     */
    public function splitUser($user) {
        if (strpos($user, '/')) {
            return explode('/', $user, 2);
        } elseif (strpos($user, '\\')) {
            return explode('\\', $user);
        } else {
            return array(null, $user);
        }
    }

    /**
     * @return string
     */
    public function getAuthString() {
        return $this->user . '%' . $this->password;
    }

    /**
     * @return string
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getWorkgroup() {
        return $this->workgroup;
    }

    /**
     * @return \Icewind\SMB\IShare[]
     *
     * @throws \Icewind\SMB\Exception\AuthenticationException
     * @throws \Icewind\SMB\Exception\InvalidHostException
     */
    public function listShares() {
        $workgroupArgument = ($this->workgroup) ? ' -W ' . escapeshellarg($this->workgroup) : '';
        $maxProtocolArgument = ($this->maxProtocol) ? ' -m ' . escapeshellarg($this->maxProtocol) : '';
        $command = sprintf('%s %s %s --authentication-file=%s -gL %s', $this->system->getSmbclientPath(), $workgroupArgument, $maxProtocolArgument,System::getFD(3), escapeshellarg($this->getHost())
        );
        $connection = new RawConnection($command);
        $connection->writeAuthentication($this->getUser(), $this->getPassword());
        $output = $connection->readAll();
        $parser = new Parser($this->timezoneProvider);

        $parser->checkConnectionError($output[0]);

        $shareNames = $parser->parseListShares($output);

        $shares = array();
        foreach ($shareNames as $name => $description) {
            $shares[] = $this->getShare($name);
        }
        return $shares;
    }

    /**
     * @param string $name
     * @return \Icewind\SMB\IShare
     */
    public function getShare($name) {
        return new Share($this, $name, $this->system);
    }

    /**
     * @return string
     */
    public function getTimeZone() {
        return $this->timezoneProvider->get();
    }

    /**
     * @return integer
     */
    public function getMaxProtocol() {
        return $this->maxProtocol;
    }

    /**
     * @param integer $maxProtocol
     */
    public function setMaxProtocol($maxProtocol) {
        if((ctype_digit(strval($maxProtocol)))){
             $this->maxProtocol = 'SMB' . $maxProtocol;
        } else {
            throw new Exception\InvalidTypeException("Parameter is invalid, integer type only is accepted");
        }
    }
}
