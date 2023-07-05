<?php

namespace Mervus\GithubIssueWriter\Exceptions;

use Exception;

class GithubTokenNotSetException extends Exception
{
    public function __construct()
    {
        parent::__construct("GITHUB_TOKEN not set in .env file");
    }
}
