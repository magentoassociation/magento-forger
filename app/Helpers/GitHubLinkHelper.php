<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Helpers;

class GitHubLinkHelper
{
    /**
     * Build a browser URL to the configured repo's open issues filtered by a single label.
     *
     * The label string is the single source of truth: the query is generated and encoded
     * here so callers never hand-encode `&`, `/`, or spaces.
     *
     * @param  string  $label  Exact GitHub label name, e.g. "Area: Cart & Checkout".
     * @param  bool  $unclaimed  Exclude issues already assigned or with a linked PR, so a
     *                           contributor lands only on work nobody has picked up.
     * @return string Absolute github.com issue-search URL.
     */
    public static function issueLabelUrl(string $label, bool $unclaimed = false): string
    {
        $repo = config('github.repo');
        $query = sprintf('is:issue is:open label:"%s"', $label);
        if ($unclaimed) {
            $query .= ' no:assignee -linked:pr';
        }

        return sprintf('https://github.com/%s/issues?q=%s', $repo, urlencode($query));
    }

    /**
     * Build a browser URL to the configured repo's open pull requests filtered by a
     * single label — e.g. the "pending review" label a maintainer would pick up.
     *
     * @param  string  $label  Exact GitHub label name.
     * @return string Absolute github.com PR-search URL.
     */
    public static function pullRequestLabelUrl(string $label): string
    {
        $repo = config('github.repo');
        $query = sprintf('is:pr is:open label:"%s"', $label);

        return sprintf('https://github.com/%s/pulls?q=%s', $repo, urlencode($query));
    }
}
