

<h3 align="center">github-issue-writer</h3>

  <p align="center">
    A small library to write Github Issues fast based on Exceptions
    <br />
    <a href="https://github.com/Mervus/github-issue-writer"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/Mervus/github-issue-writer/issues">Report Bug</a>
    ·
    <a href="https://github.com/Mervus/github-issue-writer/issues">Request Feature</a>
  </p>

<!-- GETTING STARTED -->
## Getting Started

This is an example of how you get a local copy up and running.

### Prerequisites

You need Composer and PHP
### Installation

1. Add with Composer
   ```sh
   composer require mervus/github-issue-writer
    ```
2. In your .env add
   ```sh
   GITHUB_TOKEN=your-token
   GITHUB_REPO=<mervus/github-issue-writer>
   ```
3. Refresh Config
    ```sh
    php artisan config:clear
    ```

### Usage
```php
$exception = new \Exception("test");

GithubIssue::createFromException($exception);
```

```php
$exception = new \Exception("test");

$issue = (new GithubIssue(String $title, Exception $exception));
$issue->create();
```
