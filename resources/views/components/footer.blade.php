<footer class="page-footer mt-auto site-footer-dark" role="contentinfo">
    <div class="chrome-hairline"></div>

    <div class="container sf-inner">
        <div class="sf-body">
            {{-- Slack call to action --}}
            <div class="sf-cta">
                <h2 class="sf-heading">Join the conversation on the Magento Association Slack</h2>
                @if ($slackInviteUrl = config('homepage.slack_invite_url'))
                    <a href="{{ $slackInviteUrl }}" target="_blank" rel="noopener" class="sf-slack">
                        <i class="fab fa-slack" style="font-size: 15px;"></i> Join Our Slack
                    </a>
                @endif
            </div>

            {{-- Link columns --}}
            <div class="sf-cols">
                <nav class="sf-col" aria-label="Forger">
                    <h3 class="sf-col-head">Forger</h3>
                    <a href="{{ route('leaderboard.index') }}">Leaderboard</a>
                    <a href="{{ route('issues.issuesByMonth') }}">Issues</a>
                    <a href="{{ route('prs.PRsByMonth') }}">Pull requests</a>
                    <a href="{{ route('leaderboard.scoring') }}">How scores work</a>
                </nav>
                <nav class="sf-col" aria-label="Community">
                    <h3 class="sf-col-head">Community</h3>
                    <a href="https://magentoassociation.org" target="_blank" rel="noopener">Magento Association</a>
                    <a href="https://www.magento-opensource.com/" target="_blank" rel="noopener">Magento Open Source</a>
                    <a href="https://mage-os.org/" target="_blank" rel="noopener">Mage-OS</a>
                    <a href="https://meet-magento.com" target="_blank" rel="noopener">Meet Magento</a>
                    <a href="https://github.com/magento/magento2" target="_blank" rel="noopener">Magento on GitHub</a>
                </nav>
            </div>
        </div>
    </div>

    {{-- Legal strip: rule is full-bleed, text sits in the container --}}
    <div class="sf-legal">
        <div class="container">
            <p>
                Magento, Meet Magento and all related logos are either registered trademarks or
                trademarks of Adobe Inc. in the United States and/or other countries. Use of such
                trademarks is under license and does not imply any affiliation, endorsement, or
                sponsorship by Adobe Inc.
            </p>
        </div>
    </div>
</footer>
