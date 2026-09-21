<div class="modal fade sc-modal" id="scoringModal" tabindex="-1" aria-labelledby="scoringModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title sc-title" id="scoringModalLabel">How {{ strtolower($boards[$board]) }} scores are tallied</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @include('leaderboard._scoring-content', ['scoring' => $scoring, 'boards' => $boards, 'board' => $board])
            </div>
            <div class="modal-footer">
                <button type="button" class="sc-close" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
