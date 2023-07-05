<?php

namespace Mervus\GithubIssueWriter;

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


    public function __construct(string $title, Exception $exception)
    {
        $this->isProduction = App::isProduction();

        $this->title = $title;
        $this->message = $exception->getMessage();
        $this->exception = $exception;
        $this->isError = true;
        $this->state = "";
        $this->state_reason = "";
        $this->assignees = [];
        $this->token  = env('GITHUB_TOKEN');
        $this->issueNumber = 0;

        if ($this->token == null)
            throw new \Exception('GITHUB_TOKEN not set in .env');

        $this->editIssueIfExists();
    }
    public function setIssueById(int $id) : GithubIssue
    {
        $this->issueNumber = $id;
        return $this;
    }
    public function isNotAError() : bool
    {
        $this->isError = false;
        return $this;
    }

    public static function createFromException(\Exception $e) : GithubIssue
    {
        $instance = new self($e->getMessage(), $e->getTraceAsString());

        return $instance->create();
    }

    public function setAssignee(GithubUser $user) : GithubIssue
    {
        $this->assignees = [...$this->assignees, getGithubUser($user)];
        return $this;
    }

    public function create() : GithubIssue
    {
        $IS_NOT_PRODUCTION = !$this->isProduction;
        if ($IS_NOT_PRODUCTION)
            throw new Exception("Is not In Production Wont create issue in non production environment", );

        $url = "https://api.github.com/repos/creationx/toolboxx_react/issues" ;

        $title = htmlspecialchars(stripslashes( $this->title), ENT_QUOTES);
        $body = htmlspecialchars(stripslashes($this->message), ENT_QUOTES);

        if ($this->issueNumber != 0)
            return $this->addCommentWithNewError();

        $client = $this->createGuzzleInstance();

        $json = [
            'title' => $title,
            'body' => $body,
            'labels' => $this->isError ? ['bug', 'automated'] : ['automated'],#
            'assignee' => $this->assignees, //NEEDS PUSH ACCESS TO WORK
        ];

        if ($this->issueNumber != 0)
        {
            $json['state'] = $this->state;
            $json['state_reason'] = $this->state_reason;
        }

        $res = $client->request('POST', $url,
            [
                $json
        ]);

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
        $res = $this->createGuzzleInstance()->request("POST", "https://api.github.com/repos/creationx/toolboxx_react/issues/{$this->issueNumber}/comments", [
            'json' => [
                'body' => htmlspecialchars(stripslashes($this->message), ENT_QUOTES),
            ]
        ]);

        return $this;
    }
    public function getUrl() : string
    {
        if ($this->issueNumber == null)
            throw new \Exception('Issue not created yet');

        return "https://github.com/creationx/toolboxx_react/issues/{$this->issueNumber}";
    }
    private function createGuzzleInstance() : Client
    {
        return new \GuzzleHttp\Client(
            [
                "headers" => [
                    'Authorization' => 'Bearer ' . $this->token,
                ]
            ]
        );

    }

    private function editIssueIfExists(): void
    {
        $client = $this->createGuzzleInstance();

        $res = $client->request('GET', "https://api.github.com/repos/creationx/toolboxx_react/issues", [
            'query' => [
                'labels' => 'automated',
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
                $this->state_reason = "reopened";
            }
        }
    }
}