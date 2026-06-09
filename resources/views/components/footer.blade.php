<footer class="page-footer mt-auto">
    {{-- Newsletter signup — disabled. The magento-opensource.com endpoint validates a
         server-issued form_key + invisible reCAPTCHA, so a cross-domain post will not work.
         Restore once a local newsletter route exists. --}}
    {{--
    <div class="bg-primary py-5 w-full text-center">
        <div class="container">
            <span class="text-white fs-3 fw-light d-inline-block mb-4">
                Sign Up to our Newsletter for exclusive updates
            </span>

            <form class="w-100"
                  action="https://www.magento-opensource.com/newsletter/subscriber/new/"
                  method="post"
                  aria-label="Subscribe to Newsletter">
                <div class="d-flex mx-auto" style="max-width: 800px;">
                    <label for="newsletter-subscribe" class="visually-hidden">Email Address</label>
                    <input name="email"
                           type="email"
                           required
                           id="newsletter-subscribe"
                           class="form-control form-control-lg border-0"
                           placeholder="Enter your email address"
                           autocomplete="email"
                           style="border-radius: 50px 0 0 50px;">
                    <button type="submit"
                            class="btn btn-dark btn-lg flex-shrink-0"
                            style="border-radius: 0 50px 50px 0;">
                        Subscribe
                    </button>
                </div>
            </form>
        </div>
    </div>
    --}}

    {{-- Join the Magento Association Slack (invite URL rotated via SLACK_INVITE_URL) --}}
    @if ($slackInviteUrl = config('homepage.slack_invite_url'))
        <div class="bg-primary py-5 w-full text-center">
            <div class="container">
                <span class="text-white fs-3 fw-light d-inline-block mb-4">
                    Join the conversation on the Magento Association Slack
                </span>
                <div>
                    <a href="{{ $slackInviteUrl }}"
                       target="_blank"
                       rel="noopener"
                       class="btn btn-dark btn-lg rounded-pill">
                        <i class="fab fa-slack"></i> Join our Slack
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Copyright / trademark --}}
    <div class="text-white" style="background-color: #2b2b2b;">
        <div class="container py-4 mx-auto text-center">
            <p class="mb-0 small" style="color: #cbd5e1;">
                Magento, Meet Magento and all related logos are either registered trademarks or
                trademarks of Adobe Inc. in the United States and/or other Countries. Use of such
                trademarks is under license and does not imply any affiliation, endorsement, or
                sponsorship by Adobe Inc.
            </p>
        </div>
    </div>
</footer>
