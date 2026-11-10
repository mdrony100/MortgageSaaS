# Live results from this VM

The app, Redis queue worker, and Python parser were running. These rows are live-processed, not PHPUnit.

| Workflow | Result | How | Detail |
|---|---|---|---|
| login verified dev account | Pass | live HTTP | HTTP 200 |
| created second org and broker | Pass | live artisan | 4 5 |
| tenant isolation | Pass | live HTTP | other org GET file HTTP 403 |
| uploaded 12 personal statements | Pass | live HTTP + queue | 12 versions |
| queued personal 12-month run | Pass | live HTTP | HTTP 202 run 0d80043d-5226-46ac-bee7-6680cff64ba8 |
| queue worker finished personal run | Pass | live Redis queue worker | PartiallyCompleted |
| personal 12 expected vs actual | Pass | live-processed in the VM | observed 5330000 eligible 4800000 monthly 4000.00 nsf {'nsf_events': 1, 'nsf_fee_minor': 3500, 'overdraft_events': 1, 'overdraft_fee_minor': 3500} |
| DSCR 3300/3000 and open-finding export | Pass | live-processed in the VM | display 1.10 report HTTP 201 run NeedsReview |
| billing sandbox read | Pass | live HTTP | provider fake |
| broker share, restricted bank view, revoke | Pass | live HTTP | before 403 during 200 audience broker after 403 |
