# GitHub GraphQL Queries

This directory contains GraphQL queries for fetching GitHub repository data.

## Table of Contents

- [`github_issue_count.graphql`](#github_issue_countgraphql)
- [`github_issues.graphql`](#github_issuesgraphql)
- [`github_single_issue_or_pr_interactions.graphql`](#github_single_issue_or_pr_interactionsgraphql)
- [`github_issues_with_interactions.graphql`](#github_issues_with_interactionsgraphql)
- [`github_issues_with_events.graphql`](#github_issues_with_eventsgraphql)
- [`github_pull_request_count.graphql`](#github_pull_request_countgraphql)
- [`github_pull_requests.graphql`](#github_pull_requestsgraphql)

## Queries

### `github_issue_count.graphql`
Fetches issue statistics for a repository.

**Parameters:**
- `owner`: Repository owner username
- `name`: Repository name

**Returns:**
- Total count of all issues
- Count of open issues
- Count of closed issues

**Used in:**
- `app/Services/GitHub/GitHubService.php` - `fetchIssueCount()` method

### `github_issues.graphql`
Fetches a paginated list of issues with basic metadata and interaction counts.

**Parameters:**
- `owner`: Repository owner username
- `name`: Repository name
- `cursor`: Pagination cursor (optional)

**Returns:**
- Rate limit information
- Paginated issues (100 per page, sorted by most recently updated)
- For each issue: id, number, title, url, state, creation/update/close timestamps, author, comment count, label names

**Used in:**
- `app/Services/GitHub/GitHubService.php` - `fetchIssues()` method
- `app/Console/Commands/SyncGitHubIssues.php` - Main issue syncing command

### `github_single_issue_or_pr_interactions.graphql`
Fetches detailed interaction data for a single issue or pull request.

**Parameters:**
- `owner`: Repository owner username
- `name`: Repository name
- `number`: Issue or pull request number

**Returns:**
- For issues: author, creation timestamp, up to 100 comments with author and timestamp, up to 100 timeline events (assigned, closed, labeled, unlabeled) with actor and timestamp
- For pull requests: author, creation/update/merge timestamps

**Used in:**
- `app/Services/GitHub/GitHubService.php` - `fetchInteractionsForIssue()` method

### `github_issues_with_interactions.graphql`
Fetches a paginated list of issues with interaction data.

**Parameters:**
- `owner`: Repository owner username
- `name`: Repository name
- `cursor`: Pagination cursor (optional)

**Returns:**
- Rate limit information
- Paginated issues (25 per page, sorted by most recently updated)
- For each issue: id, number, title, url, state, creation/update/close timestamps, author, up to 100 comments with author and timestamp, up to 100 timeline events (assigned, closed, labeled, unlabeled) with actor and timestamp

**Used in:**
- `app/Services/GitHub/GitHubService.php` - `fetchIssuesWithInteractions()` method

### `github_issues_with_events.graphql`
Fetches a paginated list of issues with comprehensive timeline event data.

**Parameters:**
- `owner`: Repository owner username
- `name`: Repository name
- `cursor`: Pagination cursor (optional)

**Returns:**
- Rate limit information
- Paginated issues (25 per page, sorted by most recently updated)
- For each issue: number, up to 100 timeline events including assigned, unassigned, closed, reopened, labeled, unlabeled, referenced, cross-referenced, renamed title, locked, unlocked, pinned, unpinned, milestoned, demilestoned, marked as duplicate, and transferred events with actor and timestamp

**Used in:**
- `app/Services/GitHub/GitHubService.php` - `fetchIssuesWithEvents()` method

### `github_pull_request_count.graphql`
Fetches pull request statistics for a repository.

**Parameters:**
- `owner`: Repository owner username
- `name`: Repository name

**Returns:**
- Total count of all pull requests
- Count of open pull requests
- Count of merged pull requests
- Count of closed pull requests

**Used in:**
- `app/Services/GitHub/GitHubService.php` - `fetchPullRequestCount()` method

### `github_pull_requests.graphql`
Fetches a paginated list of pull requests with metadata and interaction counts.

**Parameters:**
- `owner`: Repository owner username
- `name`: Repository name
- `cursor`: Pagination cursor (optional)

**Returns:**
- Rate limit information
- Paginated pull requests (100 per page, sorted by most recently updated)
- For each pull request: id, number, title, url, state, draft status, creation/update/merge/close timestamps, author, comment count, review count, up to 10 label names

**Used in:**
- `app/Services/GitHub/GitHubService.php` - `fetchPullRequests()` method

