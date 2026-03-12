# Magento Forger

A Laravel application for analyzing GitHub issues and pull requests for the Magento project. This application provides insights into GitHub activity through interactive charts and monthly breakdowns.

## Features

- **Monthly GitHub Stats**: Interactive charts showing pull requests and issues over time
- **Issues by Month**: Detailed monthly breakdown of GitHub issues with direct links to GitHub
- **PRs by Month**: Detailed monthly breakdown of GitHub pull requests with direct links to GitHub
- **Label Analysis**: Comprehensive view of all GitHub labels with issue counts
- **OpenSearch Integration**: Fast search and aggregation capabilities for large datasets

## Prerequisites

- **PHP 8.3+**
- **Node.js 18+** and **npm**
- **Composer**
- **DDEV** (recommended for local development)

## Installation

### 1. Install DDEV

DDEV is the recommended way to run this project locally as it provides OpenSearch, Redis, and other services out of the box.

#### Linux
```bash
# Install using the install script
curl -fsSL https://ddev.com/install.sh | bash

# Or install manually
sudo apt-get update && sudo apt-get install -y curl
curl -LO https://github.com/ddev/ddev/releases/download/v1.22.7/ddev_linux-amd64.tar.gz
tar -xzf ddev_linux-amd64.tar.gz
sudo mv ddev /usr/local/bin/
```

#### macOS
```bash
# Using Homebrew (recommended)
brew install ddev

# Or using the install script
curl -fsSL https://ddev.com/install.sh | bash
```

#### Windows
```powershell
# Using Chocolatey
choco install ddev

# Or download the installer from https://github.com/ddev/ddev/releases
```

### 2. Clone and Setup the Project

```bash
# Clone the repository
git clone https://github.com/TheMagentoAssociation/magento-forger.git
cd magento-forger

# Start DDEV
ddev start

# Install PHP dependencies
ddev composer install

# Install Node.js dependencies
ddev npm install

# Copy environment file
cp .env.example .env

# Generate application key
ddev artisan key:generate
```

### 3. Configure Environment Variables

Edit your `.env` file to add the required configuration:

```bash
# Edit the .env file
nano .env  # or use your preferred editor
```

Add the following configuration sections to your `.env` file:

#### OpenSearch Configuration
```env
# OpenSearch settings (DDEV provides these automatically)
OPENSEARCH_HOST=opensearch
OPENSEARCH_PORT=9200
```

#### GitHub Configuration
```env
# GitHub API settings
GITHUB_REPO=magento/magento2
GITHUB_TOKEN=your_github_personal_access_token_here
GITHUB_CLIENT_ID=your_client_id_here
GITHUB_CLIENT_SECRET=your_client_secret_here
GITHUB_REDIRECT_URI=your_local_ddev_url/auth/github/callback
```

**Important**: You need to create a GitHub Personal Access Token:

1. Go to [GitHub Settings > Developer settings > Personal access tokens](https://github.com/settings/tokens)
2. Click "Generate new token (classic)"
3. Give it a descriptive name like "Magento Forger"
4. Select the following scopes:
   - `public_repo` (to read public repository data)
   - `read:org` (if analyzing organization repositories)
5. Copy the generated token and paste it as the `GITHUB_TOKEN` value


**Create a Local GitHub OAuth App**
1. Go to: https://github.com/settings/developers
2. Click "OAuth Apps" → "New OAuth App"
3. Fill in these details
   1. Application name: Magento Forger Local Dev (or any name you prefer)
   2. Homepage URL: https://forger.ddev.site (your local DDEV URL)
   3. Authorization callback URL: https://forger.ddev.site/auth/github/callback
   4. Application description: (optional) "Local development OAuth for Magento Forger"
   5. Click "Register application"
   6. Click "Generate a new client secret" to get your Client Secret
4. Add OAuth Credentials to Your Local .env File

### 4. Run Database Migrations

```bash
# Run Laravel migrations to set up the database
ddev artisan migrate
```

### 5. Initial Data Sync

Before you can view any data, you need to sync GitHub issues and pull requests to OpenSearch:

```bash
# Sync GitHub issues (this may take a while for the initial sync)
ddev artisan sync:github:issues

# Sync GitHub pull requests (this may take a while for the initial sync)
ddev artisan sync:github:prs

# Sync GitHub events (this may take a long time for the initial sync)
ddev artisan sync:github:events

# Sync GitHub interactions (this will take a long time for the initial sync)
ddev artisan sync:github:interactions

```

**Note**: The initial sync can take a few minutes or hours depending on the repository size. You can monitor progress in the 
terminal. For subsequent syncs, you can use the `--since` option:

```bash
# Sync only recent data (much faster)
ddev artisan sync:github:issues --since "1 week ago"
ddev artisan sync:github:prs --since "1 week ago"
ddev artisan sync:github:events --since "1 week ago"
ddev artisan sync:github:interactions --since "1 week ago"
```

Process the interactions:
```bash
ddev artisan process:github:interactions
```

### 6. Start the Development Server

```bash
# Start Vite for asset compilation (in one terminal)
ddev npm run dev

# The Laravel server is automatically started by DDEV
# Visit your site at the URL shown by: ddev describe
```

Your application should now be available at the URL provided by `ddev describe` (typically something like `https://forger.ddev.site`).

## Usage

### Available Pages

- **Home** (`/`): Interactive charts showing monthly GitHub statistics
- **Issues by Month** (`/issuesByMonth`): Detailed breakdown of issues by month and year
- **PRs by Month** (`/prsByMonth`): Detailed breakdown of pull requests by month and year
- **All Labels** (`/labels/allLabels`): Comprehensive view of all GitHub labels with counts

### Keeping Data Updated

The application includes scheduled commands that automatically sync data every 2 hours:

```bash
# View scheduled commands
ddev artisan schedule:list

# Run the scheduler manually (for testing)
ddev artisan schedule:run
```

For production, you should set up a cron job to run the Laravel scheduler:

```bash
# Add this to your crontab
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

## Magic Explained

### Charts API

If you call /api/charts/foo, the method `foo` within `app/Http/Controllers/ChartController.php` will be called.

It is expected that this method returns a fullblown chart JSON for chart.js

### Navigation
In order to get your Route auto-linked into the main navigation, give your route a name.
If you want to create a submenu, prefix your route name with the name of the main menu item and separate by `-`.

## Development

### Running Tests

```bash
# Run the test suite
ddev artisan test

# Run tests with coverage
ddev composer test
```

### Code Style

This project uses Laravel Pint for code formatting:

```bash
# Check code style
ddev composer pint

# Fix code style issues
ddev composer pint -- --fix
```

### Useful Commands

```bash
# Clear all caches
ddev artisan optimize:clear

# View application routes
ddev artisan route:list

# Access the database
ddev mysql

# View logs in real-time
ddev artisan pail

# Access OpenSearch directly
curl http://opensearch:9200/_cat/indices

# Run all development services (server, queue, logs, vite)
ddev composer dev
```

## Deploying
The main way to deploy this project will be through our GHA which will run on _merge to main_ or via _workflow dispatch_. 
However we can deploy from **local** if required.

**Please Note:** the required tools are needed for the deployment process to work, the required software is required to ensure you do not upload a broken package. Improvements may be made in future to use the `ddev` to avoid that issue

### Prerequired Tools
* `composer`
* `deployer` - `composer global require deployer/deployer`
* `sshpass`  - `brew install sshpass` or `apt update && apt install sshpass`

### Prerequired software
* PHP 8.2
* Node v18

### Optional Tools
* `act` Github Actions Locally - `brew install act` or `curl --proto '=https' --tlsv1.2 -sSf https://raw.githubusercontent.com/nektos/act/master/install.sh | sudo bash`

### Execute a deploy
1. `cp .env.template .env.deploy`
2. Add to the bottom of `.env.deploy` (This will only be used by deployer, they will be ripped out before deploying)
    ```
    SSH_HOST=...
    SSH_PORT=...
    SSH_USER=...
    SSH_PATH=...
    SSHPASS=...
    ```
3. Update `.env.deploy` with correct credentials from your host for production
4. `bash ./deploy.sh` or `act --container-architecture linux/amd64 --secret-file .env.deploy -j build-and-deploy -W .github/workflows/deploy.yml`

## Troubleshooting

### Common Issues

**1. OpenSearch connection errors**
```bash
# Check if OpenSearch is running
ddev describe

# Restart DDEV services
ddev restart
```

**2. GitHub API rate limiting**
- Ensure you have a valid GitHub token configured
- The application respects rate limits and will wait when necessary
- Consider using a GitHub App token for higher rate limits

**3. Vite assets not loading**
```bash
# Ensure Vite is running
ddev npm run dev

# Clear Laravel caches
ddev artisan view:clear
ddev artisan config:clear
```

**4. Database issues**
```bash
# Reset the database
ddev artisan migrate:fresh

# Check database connection
ddev artisan tinker
# Then run: DB::connection()->getPdo();
```

### Performance Tips

- Use the `--since` option for incremental syncs after the initial data load
- Monitor OpenSearch disk usage as GitHub data can be substantial
- Consider setting up index lifecycle policies for data retention

## Architecture

This application uses:

- **Laravel 12** - PHP framework
- **OpenSearch** - Search and analytics engine for GitHub data
- **Vite** - Frontend build tool
- **Chart.js** - Interactive charts
- **Tailwind CSS** - Utility-first CSS framework
- **Bootstrap** - UI components
- **DDEV** - Local development environment

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [OSL License](https://opensource.org/licenses/OSL-3.0).
