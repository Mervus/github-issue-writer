<?php

namespace Mervus\GithubIssueWriter;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Mervus\GithubIssueWriter\Exceptions\GithubTokenNotSetException;

class GithubIssue
{
    private string $title;
    private string $message;
    private Exception $exception;
    private string|null $token;
    private string $state;
    private array $assignees;
    private string $state_reason;
    private int $issueNumber;
    private bool $isError;
    private bool $isProduction;
    private string $repository;

    public function __construct(string $title, Exception $exception)
    {
        $this->isProduction = getenv('APP_ENV') == 'production';

        $this->title = $title;
        $this->message = $exception->getMessage();
        $this->exception = $exception;
        $this->isError = true;
        $this->state = "";
        $this->state_reason = "";
        $this->assignees = [];
        $this->token  = getenv('GITHUB_TOKEN');
        $this->repository = getenv('GITHUB_REPO');
        $this->issueNumber = 0;

        if ($this->token == null)
            throw new GithubTokenNotSetException();

        $this->editIssueIfExists();
    }

    public function setIssueById(int $id) : GithubIssue
    {
        $this->issueNumber = $id;
        return $this;
    }
    public function isNotAnError() : bool
    {
        $this->isError = false;
        return $this;
    }

    public static function createFromException(\Exception $e) : GithubIssue
    {
        $instance = new self($e->getMessage(), $e);

        return $instance->create();
    }

    public function create() : ?GithubIssue
    {
        if (!$this->isProduction)
        {
            Log::info("GithubIssueWriter: Not in production, not creating issue");
            return null;
        }

        $url = "https://api.github.com/repos/$this->repository/issues" ;

        if ($this->issueNumber != 0)
        {
            if ($this->state_reason == "reopened")
                return $this->reopenIssue();
            else
                return $this->addCommentWithNewError();
        }

        $client = $this->createGuzzleInstance();

        $json = $this->createRequestJson();


        $res = $client->request('POST', $url, ["json" => $json]);

        $this->issueNumber =  json_decode($res->getBody()->getContents())->number;
        return $this;
    }
    public function writeToLaravelLog() : GithubIssue
    {
        Log::error($this->message, $this->exception->getTrace());
        return $this;
    }
    //TODO private
    public function addCommentWithNewError() : GithubIssue
    {
        $res = $this->createGuzzleInstance()->request("POST", "https://api.github.com/repos/$this->repository/issues/{$this->issueNumber}/comments", [
            'json' => [
                'body' => htmlspecialchars(stripslashes($this->message), ENT_QUOTES),
            ]
        ]);

        return $this;
    }

    public function getUrl() : string
    {
        if ($this->issueNumber == 0)
            throw new Exception("Issue not created yet");

        return "https://github.com/$this->repository/issues/{$this->issueNumber}";
    }
    private function createGuzzleInstance() : Client
    {
        return new Client(
            [
                "headers" => [
                    'Authorization' => 'Bearer ' . $this->token,
                    "Accept" => "application/vnd.github+json"
                ]
            ]
        );

    }
    private function createRequestJson()
    {
        $title = htmlspecialchars(stripslashes( $this->title), ENT_QUOTES);
        $body = htmlspecialchars(stripslashes($this->message), ENT_QUOTES);

        return  [
            'title' => $title,
            'body' => $body,
            'labels' => $this->isError ? ['bug'] : [''],#
            'assignees' => $this->assignees, //NEEDS PUSH ACCESS TO WORK
        ];
    }

    private function editIssueIfExists(): void
    {
        $client = $this->createGuzzleInstance();

        $res = $client->request('GET', "https://api.github.com/repos/$this->repository/issues", [
            'query' => [
                'labels' => '',
                'state' => 'all'
            ]
        ]);

        $issues_list = json_decode($res->getBody()->getContents());
        foreach ($issues_list as $issue)
        {
            if ($issue->title == $this->title)
            {
                $this->issueNumber = $issue->number;
                $this->state = "open";
                if ($issue->state == "closed")
                    $this->state_reason = "reopened";
            }
        }
    }

    private function reopenIssue()
    {
        $json = $this->createRequestJson();
        $json['state'] = $this->state;
        $json['state_reason'] = $this->state_reason;

        $res = $this->createGuzzleInstance()->patch("https://api.github.com/repos/$this->repository/issues/{$this->issueNumber}", ["json" => $json]);

        return $this;
    }
}
